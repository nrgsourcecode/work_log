#!/bin/bash

service_name=site_blocker
script_dir="$(cd -- "$(dirname -- "$0")" && pwd)"
parent_dir="$(dirname "$script_dir")"
source_path="$script_dir/site_blocker.service"
target_path=/etc/systemd/system/

sudo cp "$source_path" "$target_path/$service_name.service"
sudo systemctl daemon-reload

file_path=/etc/sudoers.d/$USER

cat <<EOF | sudo tee "$file_path" > /dev/null
$USER ALL=(root) NOPASSWD: /usr/bin/systemctl enable site_blocker.service
$USER ALL=(root) NOPASSWD: /usr/bin/systemctl start site_blocker.service
$USER ALL=(root) NOPASSWD: /usr/bin/chattr +i -- $parent_dir/settings.json
EOF

sudo chmod 440 "$file_path"
sudo chattr +i -- "$parent_dir/settings.json"
sudo systemctl enable --now "$service_name.service"
