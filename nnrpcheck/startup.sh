#!/bin/bash

/usr/bin/php sbin/bannnrp.php &
sudo -u news /usr/bin/php bin/checknnrp.php &
exit
