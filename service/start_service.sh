#!/bin/bash

source_path="$(dirname $0)/work_log.service"
target_path=~/.config/systemd/user/
mkdir -p $target_path
cp $source_path $target_path
systemctl --user daemon-reload
systemctl --user enable work_log
systemctl --user start work_log
