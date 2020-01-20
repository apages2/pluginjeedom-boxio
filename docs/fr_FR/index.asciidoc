Description
===
Plugin permettant d'utiliser l'adaptateur USB/CPL de Legrand (88213)

Configuration
===
Le plugin BOXIO permet de dialoguer avec l'ensemble des périphériques In One By Legrand que ce soit en CPL, Radiofréquence ou Infrarouge (pour ces deux derniers, il est nécessaire d'avoir un bridge CPL/IR ou CPL/RF).

Après l'avoir téléchargé sur le Market, il sera nécessaire de configurer le port sur lequel est connecté le module USB/CPL, ainsi que la vitesse du port. En général : /dev/ttyACM0:115200. Une liste déroulante propose les ports USB actifs. Le port de socket interne : 55002 est le port par défaut utilisé par le daemon Boxio. Il vaut mieux éviter de le changer sans connaitre le fonctionnement du daemon.

![configuration01](../images/boxio1.png)

Une fois configuré, on accède à la page du plugin BOXIO.

A gauche, la liste des modules BOXIO, et au centre les onglets Général, Information et Commandes.

![configuration02](../images/boxio2.png)

Le menu à gauche présente l'ensemble des modules BOXIO détectés et/ou configurés sur son installation domotique. Pour l'instant le plugin détecte les modules Legrand, mais ne les reconnait pas automatiquement. Une fois que Jeedom a détecté le nouveau module, il va le créer, mais sans lui affecter de commande. Pour cela, il sera nécessaire soit de choisir un module dans la liste déroulante complétement à droite (si le module existe dans la base de données), soit de créer les commandes une à une.

Le bouton "Ajouter équipement" permet d'ajouter des équipements spécifiques BOXIO, en générale pour des tests ou des commandes de type "Managements" ou "Spéciales".

![configuration03](../images/boxio3.png)

Lorsqu'on passe en mode Expert, on a accès à d'autres options : Type de commande, unit, type de communication, trame brute.

Le champ type permet de choisir entre une commande de type action ou une commande de type info, le type de l'action ou de l'info (Action, curseur, message, etc...) et l'action (Move Down, Move Up, etc...).
Le champ unit permet de saisir l'unit utilisée pour la commande ou pour le retour d'état.
Le champ communication permet de choisir le type de communication (Multicast, Unicast ou Broadcast).
Le champ LogicalID ou commande brute permet de nommer l'info ou de renseigner la trame "brute".

![configuration04](../images/boxio4.png)
 
L'onglet Information précise le type de l'équipement.

![configuration05](../images/boxio6.png)

L'onglet général permet de choisir le nom de l'équipement, sa destination dans l'arborescence de sa domotique, la catégorie du module (dans le jargon Legrand : WHO), la possibilité de rendre inactif le module dans Jeedom, ou encore de rendre visible ou invisible le module dans l'interface et le type de média : CPL, IR ou RF.

![configuration06](../images/boxio7.png)

L'onglet Commandes détaille l'ensemble des commandes (certains éléments ne sont disponibles qu'en mode expert).

Ces commandes sont automatiquement remplies si on choisit le type de module dans le champ "Equipements". Les paramètres utiles sont Historiser, Afficher(la commande), Evènement (permet de forcer la demande d'info sur le module).

![configuration07](../images/boxio8.png)

Dans une prochaine version et avec l'aide de tous, on pourrait imaginer que les modules soient reconnus automatiquement

Liste des modules connus
===
-    03600 : Inter Multifonction Lexic
-    03809 : Gestionnaire d'Energie
-    67201 : Inter simple
-    67202 : Inter double
-    67203 : Inter simple à voyant
-    67204 : Inter double à voyant
-    67208 : Inter Scenario d'éclairage
-    67210 : Inter Variateur 300W
-    67212 : Inter Variateur 300W à voyant
-    67214 : Inter Variateur 600W à voyant 
-    67215 : Inter Auto Emetteur-Recepteur CPL
-    67232 : Inter Variateur 600W Radio
-    67251 : Volet roulant sans dérogation
-    67255 : Volet roulant avec dérogation
-    67280 : Interscenario Emetteur CPL
-    67445 : Sortie de Cable CPL
-    88203 : Prisinter Variateur 500W

Pas encore fonctionnel ou partiel (à la recherche de Beta Testeur)
------------------------------------------------------------------

-    03606 : Interface Radio
-    67253 : Inter Centralisé Volet Roulant
-    67254 : Inter Centralise Quadruple Volet Roulant
-    67256 : Inter Centralisé Volet Roulant (orientation lamelles)
-    67442 : Thermostat Programmable
-    67449 : Programmateur d'ambiance
-    67451 : Inter VMC
-    84526 : Inter Individuel Volet Roulant Sagane
-    88202 : Prisinter 2500W

Ajouter un Equipement
===

La plupart des équipements sont rajoutés dans le plugin BOXIO dès qu'ils sont détectés par le module USB/CPL

Une fois le module créé dans le plugin, deux solutions s'offrent à vous. 

Soit le module existe dans le menu déroulant : Equipement et là il suffit de le choisir, puis de faire sauvegarder pour que les commandes soit automatiquement ajoutées.

Soit le module n'existe pas (encore) dans le plugin et alors il vous faudra créer les commandes une à une.

Les commandes info sont nécessaires pour récupérer l'état de l'équipement. Exemple pour les modules 67255, une info "bouton" est créée et permet  de connaitre l'état du bouton du module (appui sur move_up, sur move_down ou sur stop). Cette info permet notamment de gérer les widgets ou est utilisée pour le déclenchement de scénarios

Les commandes actions permettent d'effectuer des actions sur l’équipement. En fonction de la catégorie de l'équipement, vous aurez différents choix.

Les trames Legrand s'orientent autour de 3 variables et sont sous la forme (pour une trame de type BUS-COMMAND) *WHO*WHAT*WHERE##

Le WHO correspond à la catégorie (lumière, automatisme, etc…). Si dans la trame brute vous saisissez \#WHO\#, celle-ci sera remplacée par l'ID de la catégorie de l’équipement.

Le WHAT correspond à l'ID de l'action. Si vous saisissez \#WHAT\#, cette variable sera remplacée par le code correspondant de la commande choisie.

Enfin, le WHERE correspond à la concaténation du mode de communication (unicast, multicast, broadcast), de l'ID+UNIT et du media(CPL, RF, IR). Dans mon plugin, vous pouvez saisir \#WHERE# qui sera remplacé par le code correspondant au type de communication choisi et vous pouvez saisir \#IDUNIT# qui sera remplacé par la somme de l'ID du module multiplié par 16 et de son UNIT.

En gros, cela donne \*\#WHO\#\*\#WHAT\#*\#WHERE\#\#IDUNIT\###

En dehors de ces variables, vous pouvez saisir la trame brute directement, par exemple : \*2*2*\#12131413##

Pour connaitre tous les types de trames, valeur WHO, WHAT, WHERE, les types de communication ou les codes media, vous pouvez vous reporter au document Legrand : Open-Nitoo Specifications 

Une fois que vous avez créé toutes les commandes de votre équipement, il est possible de créer un fichier "Equipement" au format JSON. Pour cela, vous pouvez vous inspirer des modules existants.

Ensuite vous pourrez le partager avec la communauté (grâce à la fonction : Envoyer une configuration). Cela permettra de rajouter les commandes en automatique pour les prochains utilisateurs du plugin BOXIO.

Merci à vous.

FAQ
===

Troubleshooting
===

Le deamon refuse de démarrer
-----------------------------

Essayer de le démarrer en mode debug pour voir l'erreur

Lors du démarrage en mode debug j'ai une erreur avec : /tmp/boxiocmd.pid
-------------------------------------------------------------------------

Attendez une minute pour voir si le problème persiste, si c'est le cas en ssh faites : "sudo rm /tmp/boxiocmd.pid"

Lors du démarrage en mode debug j'ai : can not start server socket, another instance alreay running
----------------------------------------------------------------------------------------------------

Cela veut dire que le deamon est démarré mais que Jeedom n'arrive pas à le stopper. Vous pouvez soit redémarrer tout le système, soit en ssh faire "killall -9 boxio.py"

Mes équipements ne sont pas vus
-------------------------------

Assurez-vous d'avoir bien coché la case pour la création automatique des équipements, vérifiez que le deamon est bien en marche. Vous pouvez aussi le redémarrer en debug pour voir s'il reçoit bien les messages de vos équipements
