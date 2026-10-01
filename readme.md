# Work Timer

## Installation

Install the Chrome extension from the setup folder.

To read File names from code, the following is required:

- `${activeEditorLong}` in 'Window: Title' setting
- `•` in 'Window: Title Separator' setting

Install this Gnome extension in order to read window details in Wayland:
https://extensions.gnome.org/extension/4724/window-calls/

Please run this command:  
`bash setup/install.sh`

The site blocker is installed as a system service, enabled at boot, and restarted by systemd if it exits. The work log process also checks that the service remains enabled and active. Installation marks `settings.json` immutable so regular edits are blocked. To intentionally change it, run `sudo chattr -i settings.json`, edit the file, then run `sudo chattr +i settings.json`.
