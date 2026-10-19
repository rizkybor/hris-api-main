#!/usr/bin/env python3
"""
HRIS server agent -- PT Jendela Cakra Digital.

Runs from cron every 5 minutes and reports this server's disk, memory and
CPU usage, plus the websites it hosts (read from the nginx/apache config),
to the HRIS Backoffice (Insights > Server Storage). Standard library only
(Ubuntu ships python3); only reads the system, never writes anything
outside /etc/hris-agent.conf and /var/cache/hris-agent.

  hris-server-agent             send one report (what cron runs)
  hris-server-agent --once      same, printing the result
  hris-server-agent --uninstall remove the agent, its config and cron job
"""
import calendar
import fcntl
import glob
import json
import os
import re
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request

VERSION = "1.1.0"
CONF_PATH = "/etc/hris-agent.conf"
CACHE_DIR = "/var/cache/hris-agent"
BREAKDOWN_CACHE = os.path.join(CACHE_DIR, "breakdown.json")
LOCK_PATH = os.path.join(CACHE_DIR, "agent.lock")
CRON_PATH = "/etc/cron.d/hris-server-agent"
BIN_PATH = "/usr/local/bin/hris-server-agent"

# The folder breakdown walks the whole disk with du, so it's refreshed only
# every few hours and reused in between.
BREAKDOWN_TTL_SECONDS = 6 * 3600
BREAKDOWN_SKIP = {"proc", "sys", "dev", "run", "lost+found", "snap"}

# Site folder sizes and certificate expiry dates change slowly, so they are
# cached on the same schedule; the web server config itself is re-read on
# every run, so a new site shows up within 5 minutes.
SITES_CACHE = os.path.join(CACHE_DIR, "sites.json")
MAX_SITES = 80
WEB_ROOT = "/var/www"


def read_conf():
    conf = {}
    with open(CONF_PATH) as fh:
        for line in fh:
            if "=" in line and not line.lstrip().startswith("#"):
                key, value = line.split("=", 1)
                conf[key.strip()] = value.strip()
    return conf


def disk_usage(path="/"):
    st = os.statvfs(path)
    total = st.f_blocks * st.f_frsize
    free = st.f_bavail * st.f_frsize            # available to normal users, like df
    used = total - st.f_bfree * st.f_frsize     # same "used" df reports
    return {"mount": path, "total": total, "used": used, "free": free}


def memory_usage():
    info = {}
    with open("/proc/meminfo") as fh:
        for line in fh:
            key, value = line.split(":", 1)
            info[key] = int(value.split()[0]) * 1024
    total = info.get("MemTotal", 0)
    available = info.get("MemAvailable", info.get("MemFree", 0))
    swap_total = info.get("SwapTotal", 0)
    return {
        "total": total,
        "used": total - available,
        "available": available,
        "swap_total": swap_total,
        "swap_used": swap_total - info.get("SwapFree", 0),
    }


def _cpu_times():
    with open("/proc/stat") as fh:
        fields = [int(x) for x in fh.readline().split()[1:]]
    idle = fields[3] + (fields[4] if len(fields) > 4 else 0)  # idle + iowait
    return sum(fields), idle


def cpu_usage():
    total1, idle1 = _cpu_times()
    time.sleep(1)
    total2, idle2 = _cpu_times()
    delta = total2 - total1
    usage = 0.0 if delta <= 0 else (1 - (idle2 - idle1) / delta) * 100
    load1, load5, load15 = os.getloadavg()
    return {
        "cores": os.cpu_count() or 1,
        "usage_percent": round(usage, 1),
        "load1": round(load1, 2),
        "load5": round(load5, 2),
        "load15": round(load15, 2),
    }


def folder_breakdown():
    """Size of each top-level folder on the root filesystem (cached)."""
    try:
        if time.time() - os.path.getmtime(BREAKDOWN_CACHE) < BREAKDOWN_TTL_SECONDS:
            with open(BREAKDOWN_CACHE) as fh:
                return json.load(fh)
    except (OSError, ValueError):
        pass

    entries = []
    for name in sorted(os.listdir("/")):
        path = "/" + name
        if name in BREAKDOWN_SKIP or os.path.islink(path) or not os.path.isdir(path):
            continue
        try:
            # -x: stay on the root filesystem; low CPU/IO priority so the
            # scan never competes with the server's real work.
            out = subprocess.run(
                ["nice", "-n", "19", "ionice", "-c3", "du", "-x", "-s", "-B1", path],
                capture_output=True, text=True, timeout=600,
            )
            size = int(out.stdout.split()[0]) if out.stdout.strip() else 0
        except (subprocess.SubprocessError, ValueError, IndexError, OSError):
            continue
        if size > 0:
            entries.append({"path": path, "bytes": size})

    entries.sort(key=lambda e: e["bytes"], reverse=True)
    result = {"items": entries[:20], "measured_at": int(time.time())}
    try:
        with open(BREAKDOWN_CACHE, "w") as fh:
            json.dump(result, fh)
    except OSError:
        pass
    return result


# ---------------------------------------------------------------- websites

def _read(path):
    try:
        with open(path, errors="replace") as fh:
            return fh.read()
    except OSError:
        return ""


def _uncomment(text):
    return "\n".join(line.split("#", 1)[0] for line in text.splitlines())


def _clean(value):
    return value.strip().strip("'\"") if value else None


def _first(pattern, text):
    m = re.search(pattern, text, re.I)
    return _clean(m.group(1)) if m else None


def _nginx_blocks(text, keyword):
    """Bodies of every `<keyword> ... { }` block, nested braces included."""
    blocks, start, depth = [], None, 0
    for m in re.finditer(r"[{}]", text):
        if m.group() == "{":
            if start is None:
                if re.search(r"(^|[\s;{}])" + keyword + r"(\s+[\w.-]+)?\s*$", text[max(0, m.start() - 80):m.start()]):
                    start, depth = m.end(), 0
                continue
            depth += 1
        elif start is not None:
            if depth == 0:
                blocks.append((text[max(0, start - 80):start], text[start:m.start()]))
                start = None
            else:
                depth -= 1
    return blocks


def _nginx_sites():
    files = sorted(set(glob.glob("/etc/nginx/sites-enabled/*") + glob.glob("/etc/nginx/conf.d/*.conf")))
    texts = [_uncomment(_read(f)) for f in files if os.path.isfile(f)]

    # upstream name { server 127.0.0.1:3000; } -> the address proxy_pass uses
    upstreams = {}
    for text in texts:
        for head, body in _nginx_blocks(text, "upstream"):
            name = re.search(r"upstream\s+([\w.-]+)\s*\{?\s*$", head)
            target = _first(r"\bserver\s+([^;\s]+)", body)
            if name and target:
                upstreams[name.group(1)] = target

    sites = []
    for text in texts:
        for _, body in _nginx_blocks(text, "server"):
            names = (_first(r"\bserver_name\s+([^;]+);", body) or "").split()
            proxy = _first(r"\bproxy_pass\s+([^;]+);", body)
            if proxy:
                host = re.sub(r"^\w+://", "", proxy).split("/")[0]
                proxy = upstreams.get(host, host)
            listens = re.findall(r"\blisten\s+([^;]+);", body, re.I)
            sites.append({
                "server": "nginx",
                "domains": [n for n in names if n not in ("_", "localhost", "\"\"")],
                "root": _first(r"\broot\s+([^;]+);", body),
                "proxy": proxy,
                "cert": _first(r"\bssl_certificate\s+([^;]+);", body),
                "ssl": any("ssl" in l or "443" in l for l in listens),
                "redirect": bool(re.search(r"\breturn\s+30[1278]\b", body)),
            })
    return sites


def _apache_sites():
    sites = []
    for path in sorted(glob.glob("/etc/apache2/sites-enabled/*")):
        text = _uncomment(_read(path))
        for head, body in re.findall(r"<VirtualHost\s+([^>]*)>(.*?)</VirtualHost>", text, re.I | re.S):
            names = [_first(r"(?m)^\s*ServerName\s+(\S+)", body)] if re.search(r"ServerName", body, re.I) else []
            names += " ".join(re.findall(r"^\s*ServerAlias\s+(.+)$", body, re.I | re.M)).split()
            proxy = re.search(r"^\s*ProxyPass\s+\S+\s+(\S+)", body, re.I | re.M)
            sites.append({
                "server": "apache",
                "domains": [n for n in names if n and n != "localhost"],
                "root": _first(r"(?m)^\s*DocumentRoot\s+(\S+)", body),
                "proxy": re.sub(r"^\w+://", "", proxy.group(1)).split("/")[0] if proxy else None,
                "cert": _first(r"(?m)^\s*SSLCertificateFile\s+(\S+)", body),
                "ssl": ":443" in head or bool(re.search(r"^\s*SSLEngine\s+on", body, re.I | re.M)),
                "redirect": bool(re.search(r"^\s*(Redirect|RewriteRule)\b", body, re.I | re.M)) and not re.search(r"DocumentRoot", body, re.I),
            })
    return sites


def _listening_processes():
    """port -> (process name, working directory) of whatever listens on it."""
    result = {}
    try:
        out = subprocess.run(["ss", "-ltnpH"], capture_output=True, text=True, timeout=10).stdout
    except (subprocess.SubprocessError, OSError):
        return result
    for line in out.splitlines():
        parts = line.split()
        m = re.search(r'users:\(\("([^"]+)",pid=(\d+)', line)
        if len(parts) < 4 or not m:
            continue
        port = parts[3].rsplit(":", 1)[-1]
        try:
            cwd = os.readlink("/proc/%s/cwd" % m.group(2))
        except OSError:
            cwd = None
        result.setdefault(port, (m.group(1), cwd))
    return result


def _composer_version(project, package):
    try:
        with open(os.path.join(project, "composer.lock")) as fh:
            for pkg in json.load(fh).get("packages", []):
                if pkg.get("name") == package:
                    return pkg.get("version", "").lstrip("v")
    except (OSError, ValueError):
        pass
    return None


def _detect(site, processes):
    """Fills in type / version / project path / process for one site."""
    root = (site.get("root") or "").rstrip("/") or None
    if site.get("proxy"):
        port = site["proxy"].rsplit(":", 1)[-1] if ":" in site["proxy"] else None
        name, cwd = processes.get(port, (None, None))
        site["process"] = name
        site["path"] = cwd if cwd and cwd != "/" else root
        site["type"] = "node" if name in ("node", "nodejs", "bun", "deno") or (name or "").startswith("PM2") else "proxy"
        if site["path"] and os.path.isfile(os.path.join(site["path"], "artisan")):
            site["type"] = "laravel"
        return site
    if not root:
        site["type"] = "redirect" if site.get("redirect") else "unknown"
        site["path"] = None
        return site

    base = os.path.basename(root)
    parent = os.path.dirname(root)
    project = parent if base in ("public", "dist", "build", "web", "out") and parent not in ("/", WEB_ROOT) else root
    site["path"] = project

    if os.path.isfile(os.path.join(project, "artisan")):
        site["type"], site["version"] = "laravel", _composer_version(project, "laravel/framework")
    elif os.path.isfile(os.path.join(root, "wp-config.php")) or os.path.isfile(os.path.join(root, "wp-load.php")):
        site["type"] = "wordpress"
        m = re.search(r"\$wp_version\s*=\s*'([^']+)'", _read(os.path.join(root, "wp-includes/version.php")))
        site["version"] = m.group(1) if m else None
    elif os.path.isfile(os.path.join(project, "spark")):
        site["type"] = "codeigniter"
    elif os.path.isfile(os.path.join(root, "index.php")):
        site["type"] = "php"
    elif os.path.isfile(os.path.join(root, "index.html")):
        site["type"] = "static"
    else:
        site["type"] = "unknown"
    return site


def _cert_expiry(path):
    try:
        out = subprocess.run(["openssl", "x509", "-enddate", "-noout", "-in", path],
                             capture_output=True, text=True, timeout=10).stdout
        stamp = out.strip().split("=", 1)[1]
        return calendar.timegm(time.strptime(stamp, "%b %d %H:%M:%S %Y %Z"))
    except (subprocess.SubprocessError, OSError, IndexError, ValueError):
        return None


def _du(path):
    # Low CPU/IO priority; ionice is part of Ubuntu's util-linux but skip it
    # where it's missing rather than losing the size.
    prefix = ["nice", "-n", "19"] + (["ionice", "-c3"] if _has("ionice") else [])
    try:
        out = subprocess.run(prefix + ["du", "-x", "-s", "-k", path], capture_output=True, text=True, timeout=600)
        return int(out.stdout.split()[0]) * 1024
    except (subprocess.SubprocessError, ValueError, IndexError, OSError):
        return None


def _has(command):
    return any(os.access(os.path.join(d, command), os.X_OK) for d in os.environ.get("PATH", "/usr/bin:/bin").split(":"))


def websites():
    """Every site the web server serves, merged per domain, plus folders in
    /var/www that no site points to."""
    raw = _nginx_sites() + _apache_sites()

    # The same domain usually appears twice (port 80 redirect + 443 site).
    merged = {}
    for site in raw:
        key = site["domains"][0] if site["domains"] else (site["root"] or site["proxy"] or "default")
        cur = merged.get(key)
        if not cur:
            merged[key] = dict(site)
            continue
        cur["domains"] = cur["domains"] + [d for d in site["domains"] if d not in cur["domains"]]
        for field in ("root", "proxy", "cert"):
            cur[field] = cur.get(field) or site.get(field)
        cur["ssl"] = cur["ssl"] or site["ssl"]
        cur["redirect"] = cur["redirect"] and site["redirect"]

    processes = _listening_processes()
    sites = [_detect(s, processes) for s in merged.values()]

    covered = [s["path"] for s in sites if s.get("path")] + [s["root"] for s in sites if s.get("root")]
    if os.path.isdir(WEB_ROOT):
        for name in sorted(os.listdir(WEB_ROOT)):
            folder = os.path.join(WEB_ROOT, name)
            if not os.path.isdir(folder) or os.path.islink(folder):
                continue
            if any(c == folder or c.startswith(folder + "/") or folder.startswith(c.rstrip("/") + "/") for c in covered):
                continue
            sites.append(_detect({"server": None, "domains": [], "root": folder, "proxy": None,
                                  "cert": None, "ssl": False, "redirect": False}, processes))
            sites[-1]["type"] = "folder" if sites[-1]["type"] == "unknown" else sites[-1]["type"]
            sites[-1]["unlinked"] = True

    # Slow parts (du, openssl) reuse cached values for 6 hours.
    try:
        with open(SITES_CACHE) as fh:
            cache = json.load(fh)
    except (OSError, ValueError):
        cache = {}
    fresh, now = {}, time.time()

    def cached(key, compute):
        hit = cache.get(key)
        if hit and now - hit[1] < BREAKDOWN_TTL_SECONDS:
            fresh[key] = hit
            return hit[0]
        fresh[key] = [compute(), now]
        return fresh[key][0]

    report = []
    for site in sites[:MAX_SITES]:
        path = site.get("path")
        report.append({
            "domains": site["domains"][:20],
            "server": site.get("server"),
            "type": site.get("type"),
            "version": site.get("version"),
            "root": site.get("root"),
            "path": path,
            "proxy": site.get("proxy"),
            "process": site.get("process"),
            "unlinked": bool(site.get("unlinked")),
            "ssl": bool(site.get("ssl") or site.get("cert")),
            "ssl_expires_at": cached("cert:" + site["cert"], lambda: _cert_expiry(site["cert"])) if site.get("cert") else None,
            "bytes": cached("du:" + path, lambda: _du(path)) if path and os.path.isdir(path) else None,
        })

    try:
        with open(SITES_CACHE, "w") as fh:
            json.dump(fresh, fh)
    except OSError:
        pass
    return report


def os_name():
    try:
        with open("/etc/os-release") as fh:
            for line in fh:
                if line.startswith("PRETTY_NAME="):
                    return line.split("=", 1)[1].strip().strip('"')
    except OSError:
        pass
    return "Linux"


def uptime_seconds():
    try:
        with open("/proc/uptime") as fh:
            return int(float(fh.read().split()[0]))
    except (OSError, ValueError):
        return None


def send(conf, payload):
    request = urllib.request.Request(
        conf["REPORT_URL"],
        data=json.dumps(payload).encode(),
        method="POST",
        headers={
            "Content-Type": "application/json",
            "Accept": "application/json",
            # Own header (not Authorization) so it never reaches the
            # API's user-login token handling.
            "X-Agent-Token": conf["TOKEN"],
            "User-Agent": "hris-server-agent/" + VERSION,
        },
    )
    with urllib.request.urlopen(request, timeout=30) as response:
        return response.status, response.read().decode()


def uninstall():
    for path in (CRON_PATH, CONF_PATH, BIN_PATH):
        try:
            os.remove(path)
        except FileNotFoundError:
            pass
    subprocess.run(["rm", "-rf", CACHE_DIR])
    print("HRIS server agent removed.")


def main():
    if "--uninstall" in sys.argv:
        uninstall()
        return 0

    os.makedirs(CACHE_DIR, exist_ok=True)
    # One run at a time: a slow folder scan must not pile up cron runs.
    lock = open(LOCK_PATH, "w")
    try:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except OSError:
        return 0

    conf = read_conf()
    breakdown = folder_breakdown()
    payload = {
        "agent_version": VERSION,
        "hostname": socket.gethostname(),
        "os": os_name(),
        "uptime_seconds": uptime_seconds(),
        "disk": disk_usage("/"),
        "memory": memory_usage(),
        "cpu": cpu_usage(),
        "breakdown": breakdown.get("items", []),
        "breakdown_at": breakdown.get("measured_at"),
    }
    try:
        payload["sites"] = websites()
    except Exception:  # never let site discovery stop the core report
        payload["sites"] = []

    try:
        status, body = send(conf, payload)
    except urllib.error.HTTPError as err:
        status, body = err.code, err.read().decode(errors="replace")
    except (urllib.error.URLError, OSError) as err:
        status, body = 0, str(err)

    if "--once" in sys.argv:
        print("HTTP", status, body[:300])
    return 0 if 200 <= status < 300 else 1


if __name__ == "__main__":
    sys.exit(main())
