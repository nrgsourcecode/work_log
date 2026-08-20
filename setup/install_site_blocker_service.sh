#!/bin/bash

service_name=site_blocker
script_dir="$(cd -- "$(dirname -- "$0")" && pwd)"
parent_dir="$(dirname "$script_dir")"
source_path="$script_dir/site_blocker.service"
target_path=/etc/systemd/system/

sudo cp $source_path $target_path
sudo systemctl daemon-reload
sudo systemctl enable $service_name
sudo systemctl start $service_name

file_path=/etc/sudoers.d/$USER

cat <<EOF | sudo tee "$file_path" > /dev/null
$USER ALL=(ALL) NOPASSWD: /usr/sbin/service site_blocker start
$USER ALL=(ALL) NOPASSWD: /usr/bin/chattr +i $parent_dir/settings.json
$USER ALL=(ALL) NOPASSWD: /usr/bin/chattr +i $parent_dir/site_blocker.php
EOF

sudo chmod 644 $file_path