# ProcessExt: Additional emoncms input processors

An extension to the emoncms input processing module.

Processes include: 

- value_and_scale: Combines a value and scale input, multiplies the value by 10^scale
- pow10_input: Scales current value by 10 to the power of another input.

## Install

Module should be installed in the /var/www/emoncms/Modules directory.

Install this module either by downloading the zip file, extracting and renaming the resulting directory to processext, and placing the directory in /var/www/emoncms/Modules.

Or to install via git:

    cd /var/www/emoncms/Modules
    git clone git@github.com:emoncms/processext.git
    
    
