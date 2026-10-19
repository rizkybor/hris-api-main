#!/usr/bin/env bash
# HRIS server agent installer -- PT Jendela Cakra Digital.
# Usage (from the Backoffice, Insights > Server Storage > Add VPS):
#   curl -fsSL <this URL> | sudo HRIS_AGENT_TOKEN="<token>" bash
# Updating an installed agent needs no token -- it keeps the one it has:
#   curl -fsSL <this URL> | sudo bash
set -euo pipefail

REPORT_URL="__REPORT_URL__"
AGENT_URL="__AGENT_URL__"

if [ "$(id -u)" -ne 0 ]; then
  echo "Jalankan dengan sudo." >&2
  exit 1
fi
if [ -z "${HRIS_AGENT_TOKEN:-}" ] && [ -f /etc/hris-agent.conf ]; then
  HRIS_AGENT_TOKEN="$(sed -n 's/^TOKEN=//p' /etc/hris-agent.conf | head -n1)"
  [ -n "$HRIS_AGENT_TOKEN" ] && echo "Memperbarui agen yang sudah terpasang (token lama dipakai)."
fi
if [ -z "${HRIS_AGENT_TOKEN:-}" ]; then
  echo "HRIS_AGENT_TOKEN kosong. Salin perintah instalasi lengkap dari Backoffice." >&2
  exit 1
fi
if ! command -v python3 >/dev/null 2>&1; then
  echo "python3 tidak ditemukan. Pasang dulu: apt-get install -y python3" >&2
  exit 1
fi

echo "Mengunduh agen..."
curl -fsSL "$AGENT_URL" -o /usr/local/bin/hris-server-agent
chmod 755 /usr/local/bin/hris-server-agent

# Token readable by root only.
umask 077
printf 'TOKEN=%s\nREPORT_URL=%s\n' "$HRIS_AGENT_TOKEN" "$REPORT_URL" > /etc/hris-agent.conf
chmod 600 /etc/hris-agent.conf
umask 022
mkdir -p /var/cache/hris-agent

echo "*/5 * * * * root /usr/local/bin/hris-server-agent >/dev/null 2>&1" > /etc/cron.d/hris-server-agent
chmod 644 /etc/cron.d/hris-server-agent

echo "Mengirim laporan pertama (pengukuran folder pertama bisa memakan 1-2 menit)..."
if /usr/local/bin/hris-server-agent --once; then
  echo "Selesai. Server ini akan melapor ke Backoffice setiap 5 menit."
else
  echo "Agen terpasang, tetapi laporan pertama gagal. Periksa token atau koneksi ke $REPORT_URL" >&2
  exit 1
fi
