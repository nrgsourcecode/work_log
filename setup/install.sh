sudo apt install gnome-tweaks gnome-shell-extensions gnome-shell-extension-prefs
gnome_version=$(gnome-shell --version | awk '{print $3}' | awk -F'.' '{print $1}')
user_themes=$(curl -s https://extensions.gnome.org/extension/19/user-themes/ | grep -o "data-svm=\"[^\"]*\"" | sed 's/data-svm="//;s/"$//' | grep -o "&quot;$gnome_version&quot;: {&quot;pk&quot;: [^}]*}" | grep -o '&quot;version&quot;: [0-9]*' | grep -o '[0-9]\+')
url="https://extensions.gnome.org/extension-data/user-themegnome-shell-extensions.gcampax.github.com.v$user_themes.shell-extension.zip"
user_themes_uuid=user-theme@gnome-shell-extensions.gcampax.github.com
temp_path="/tmp/$user_themes_uuid.zip"
wget "$url" -O "$temp_path"
gnome-extensions install --force "$temp_path"
gnome-extensions enable $user_themes_uuid
killall -HUP gnome-shell

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