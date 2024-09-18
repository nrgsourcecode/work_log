#!/bin/bash

service_name=site_blocker
source_path="$(dirname $0)/site_blocker.service"
target_path=/etc/systemd/system/

sudo cp $source_path $target_path
sudo systemctl daemon-reload
sudo systemctl enable $service_name
sudo systemctl start $service_name

file_path=/etc/sudoers.d/$USER

echo "$USER ALL=(ALL) NOPASSWD: /usr/sbin/service site_blocker start" | sudo tee $file_path > /dev/null
sudo chmod 644 $file_path