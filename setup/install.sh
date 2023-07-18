# sudo apt install gnome-tweaks gnome-shell-extensions gnome-shell-extension-prefs
# gnome-extensions enable user-theme@gnome-shell-extensions.gcampax.github.com

setup_folder=$(realpath "$(dirname "$0")")
current_folder=$(pwd)

replace_text_in_file(){
    str_to_replace=$1
    replace_with=$2
    file_name=$3
    pattern="s|$str_to_replace|$replace_with|w /dev/stdout"
    options='i'

    if [[ $str_to_replace == *"\n"* ]]; then
        options='zi';
    fi

    changes=$(sed -$options "$pattern" "$file_name")
    if [ -z "$changes" ]; then
        echo "WARNING: PATTERN $str_to_replace not found in $file_name"
    fi
}

function create_user_theme() {
    theme_folder=~/.themes/"$1"/gnome-shell
    mkdir -p $theme_folder
    cd $theme_folder
    cp /usr/share/gnome-shell/theme/Yaru/gnome-shell.css .
    replace_text_in_file "-st-icon-style: symbolic" "-st-icon-style: regular" $theme_folder/gnome-shell.css
    cd $current_folder
}

create_user_theme work-log-regular

create_user_theme work-log-error
replace_text_in_file "background-color: #131313" "background-color: #ff0000" ~/.themes/work-log-error/gnome-shell/gnome-shell.css

source $setup_folder/install_service.sh
echo "Press ALT+F2 and run command r to restart the shell"