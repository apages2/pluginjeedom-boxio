#!/bin/bash

#set -x  # make sure each command is printed in the terminal

echo "Lancement de la synchronisation des templates Boxio"
echo "Changement du répertoire courant"
cd /tmp
echo "Récupération des templates (cette étape peut durer quelques minutes)"
rm -rf /tmp/plugin-boxio > /dev/null 2>&1
sudo git clone --depth=1 https://github.com/apages2/pluginjeedom-boxio.git 
if [ $? -ne 0 ]; then
    echo "Unable to fetch apages2/pluginjeedom-boxio git.Please check your internet connexion and github access"
    exit 1
fi
BASEDIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
if [ -d  ${BASEDIR}/../core/config/devices ]; then
	echo "Suppression des templates Boxio existants"
	sudo rm -fr ${BASEDIR}/../core/config/devices/*
	echo "Recopie des nouveaux templates Boxio"
	cd /tmp/pluginjeedom-boxio/core/config/devices
	sudo mv * ${BASEDIR}/../core/config/devices/
	echo "Nettoyage du répertoire temporaire"
	sudo rm -R /tmp/pluginjeedom-boxio
	sudo chown -R www-data:www-data ${BASEDIR}/../core/config/devices/
	echo "Vos templates sont maintenant à jour !"
else
	echo 'Veuillez installer les dépendances'
fi
