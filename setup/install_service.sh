#!/bin/bash

service_name=work_log
source_path="$(dirname $0)/work_log.service"
target_path=~/.config/systemd/user/

mkdir -p $target_path
cp $source_path $target_path
systemctl --user daemon-reload
systemctl --user enable $service_name
systemctl --user start $service_name
