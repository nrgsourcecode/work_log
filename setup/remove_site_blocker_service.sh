#!/bin/bash

service_name=site_blocker
service_path=/etc/systemd/system/
script_dir="$(cd -- "$(dirname -- "$0")" && pwd)"
parent_dir="$(dirname "$script_dir")"

sudo systemctl stop "$service_name.service"
sudo systemctl disable "$service_name.service"
sudo chattr -i -- "$parent_dir/settings.json" 2>/dev/null || true
sudo rm -f "$service_path/$service_name.service"
sudo systemctl daemon-reload

file_path=/etc/sudoers.d/$USER
sudo rm -rf $file_path
