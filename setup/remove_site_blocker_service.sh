#!/bin/bash

service_name=site_blocker
service_path=/etc/systemd/system/

sudo systemctl stop $service_name
sudo systemctl disable $service_name
sudo rm -rf $service_path/$service_name.service
sudo systemctl daemon-reload

file_path=/etc/sudoers.d/$USER
sudo rm -rf $file_path
