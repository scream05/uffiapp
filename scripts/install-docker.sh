#!/bin/bash
# Einmalig als root ausführen:  sudo bash scripts/install-docker.sh
set -euo pipefail
# Offizielles Docker-Repository (aktuelles Docker + Compose v2)
apt-get update
apt-get install -y ca-certificates curl
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/debian $(. /etc/os-release && echo "$VERSION_CODENAME") stable" > /etc/apt/sources.list.d/docker.list
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
systemctl enable --now docker
usermod -aG docker smartcity
# Firewall-Ports freigeben, falls ufw aktiv ist
if command -v ufw >/dev/null && ufw status | grep -q active; then
  ufw allow 80/tcp; ufw allow 443/tcp; ufw allow 443/udp
fi
# Tägliches Backup um 4:00 Uhr (als smartcity)
cat > /etc/cron.d/humhub-backup <<'CRON'
0 4 * * * smartcity /home/smartcity/humhub-setup/scripts/backup.sh >> /home/smartcity/humhub-setup/backups/backup.log 2>&1
CRON
docker --version; docker compose version
echo "Fertig. Bitte einmal ab- und wieder anmelden (oder: newgrp docker), damit die Gruppe 'docker' greift."
