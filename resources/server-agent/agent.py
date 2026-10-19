#!/usr/bin/env python3
"""
HRIS server agent -- PT Jendela Cakra Digital.

Runs from cron every 5 minutes and reports this server's disk, memory and
CPU usage to the HRIS Backoffice (Insights > Server Storage). Standard
library only (Ubuntu ships python3); reads /proc and statvfs, never writes
anything outside /etc/hris-agent.conf and /var/cache/hris-agent.

  hris-server-agent             send one report (what cron runs)
  hris-server-agent --once      same, printing the result
  hris-server-agent --uninstall remove the agent, its config and cron job
"""
import fcntl
import json
import os
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request

VERSION = "1.0.0"
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
