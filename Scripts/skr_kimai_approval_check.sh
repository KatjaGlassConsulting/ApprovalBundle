#!/bin/bash

#### INFO ####

## Author        : UBR
## Created at    : 2022-08-08
## Purpose       : check approval submissions in kimai

## Skript name   : skr_kimai_approval_check.sh
## Skript option : -c
## Skript args   : admin-not-submitted-users | teamlead-not-submitted-last-week | user-not-submitted-weeks

#####

# Parameters
  kimai_console='/var/www/html/kimai2/bin/console'
  kimai_bundle='kimai:bundle:approval:'
  kimai_command=''

# Array valid commands
  app_commands=(
   admin-not-submitted-users
   teamlead-not-submitted-last-week
   user-not-submitted-weeks
   )

# receive input parameter -c
  while getopts ':c:' command; do

    case "$command" in
     c) kimai_command="$OPTARG";;
    esac

  done

# Check input parameter for validity
  if [[ " ${app_commands[*]} " =~ " ${kimai_command} " ]]; then
    # Kimai command to excute reminder
    #   examples:
    #    /var/www/html/kimai2/bin/console kimai:bundle:approval:admin-not-submitted-users
    #    /var/www/html/kimai2/bin/console kimai:bundle:approval:teamlead-not-submitted-last-week
    #    /var/www/html/kimai2/bin/console kimai:bundle:approval:user-not-submitted-weeks
    $kimai_console $kimai_bundle$kimai_command

  else

    echo  "Command <$kimai_command> is not a valid option."

  fi
