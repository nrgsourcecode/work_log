#!/bin/bash

service_name=work_log
service_path=~/.config/systemd/user

systemctl --user stop $service_name
systemctl --user disable $service_name
rm -rf $service_path/$service_name.service
systemctl --user daemon-reload
