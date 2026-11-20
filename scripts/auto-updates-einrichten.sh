#!/bin/bash
# Einmalig als root: automatische Sicherheitsupdates für Debian (unattended-upgrades).
#   sudo bash scripts/auto-updates-einrichten.sh
# - täglich Paketlisten aktualisieren und Debian-Sicherheits- und Punkt-Updates einspielen (Debian-Standard-Quellen)
# - Docker (Repo download.docker.com) bewusst NICHT: ein Update startet dockerd und damit alle Container neu, und
#   beim Start des HumHub-Containers läuft module/update-all (Marketplace-Updates ungeprüft, siehe scripts/wachhund.sh)
#   → meldet scripts/sicherheitswache.py, Einspielen von Hand
# - kein automatischer Neustart; ist einer nötig, meldet ihn die Sicherheitswache
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Bitte mit sudo ausführen"; exit 1; }

apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y unattended-upgrades

cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
EOF

cat > /etc/apt/apt.conf.d/52rundumuffni-unattended <<'EOF'
// Ergänzung zu 50unattended-upgrades (Debian-Standard: Debian + Debian-Security), siehe scripts/auto-updates-einrichten.sh
Unattended-Upgrade::Package-Blacklist {
    "docker-ce";
    "docker-ce-cli";
    "docker-ce-rootless-extras";
    "docker-buildx-plugin";
    "docker-compose-plugin";
    "containerd.io";
};
Unattended-Upgrade::Automatic-Reboot "false";
Unattended-Upgrade::Remove-Unused-Kernel-Packages "true";
Unattended-Upgrade::Remove-Unused-Dependencies "true";
Unattended-Upgrade::SyslogEnable "true";
EOF

systemctl enable --now apt-daily.timer apt-daily-upgrade.timer
echo "--- Probelauf (zeigt, was eingespielt würde) ---"
unattended-upgrade --dry-run 2>&1 | tail -15
echo "--- Erster echter Lauf (spielt die ausstehenden Sicherheitsupdates jetzt ein) ---"
unattended-upgrade -v 2>&1 | tail -15
systemctl list-timers apt-daily.timer apt-daily-upgrade.timer --no-pager
[ -f /var/run/reboot-required ] && echo "HINWEIS: Neustart nötig (sudo reboot, Container starten von selbst)" || true
echo "Fertig. Protokoll: /var/log/unattended-upgrades/"
