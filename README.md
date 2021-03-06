Remote SPiC IDE via Xpra
========================

Ausgangslage
------------

Für das SoSe 2020 brauchen wir eine Möglichkeit, das Benutzer zuhause den SPiC IDE starten können.
Es gibt eine VM, welche aber zwingend VT-x / AMD-V benötigt.
Da dies vermutlich nicht überall aktiviert ist (und Erklärungen zum hantieren im BIOS gefährlich sind) brauchen wir eine Fallback-Lösung.

Idealerweise ohne Installation, damit man sich nicht um verschiedene Plattformen (und den Support) kümmern muss -- also web only.
Andernseits soll die Arbeitsumgebung im vollen Umfang zur Verügung stehen.


Idee
----

[Xpra](https://xpra.org/) mit [HTML5 Client](https://xpra.org/trac/wiki/Clients/HTML5) auf CIP Rechner.


Voraussetzung
-------------

 - Webserver: Apache mit *mod_php* (`libapache2-mod-php`) sowie *ssh2* (`php-ssh2`)
 - CIP Hosts: Modernes [Xpra (≥ 4.0)](https://xpra.org/trac/wiki/Download#Linux)


Vorbereitung
------------

Mittels

    make

werden Dateien (vor allem JavaScript & CSS) komprimiert, danach muss der Apache so konfiguriert werden, dass der Unterordner `wwww` das Wurzelverzeichnis für den Webserver ist.
Außerdem sollten bei Bedarf noch die Dateien unter `bin/` auf die CIP Rechner verteilt und `connect.php`, `connect.html` und `.htaccess` an die Infrastruktur angepasst werden.
Und fertig.


Ablauf
------

Der Nutzer meldet sich mit seinen CIP Daten via Web an.
Der Webserver baut eine SSH Verbindung zu einem CIP Host auf, startet dort eine Xpra Session mit der *SPiC-IDE* (oder einer anderen Anwendung) als entsprechender Nutzer.
Danach wird der Nutzer auf den HTML5 Client umgeleitet.


Schwierigkeiten
---------------

Moderne Browser unterstützen bei HTTPS [kein laden von HTTP mehr](https://blog.chromium.org/2019/10/no-more-mixed-messages-about-https.html), gilt auch für [WebSockets](https://de.wikipedia.org/wiki/WebSocket).
CIP Hosts haben kein SSL Zertifikat (und würden den Key andernfalls auch nicht für Benutzer zugreifbar machen), temporäres selbstsignieren mögen Browser auch nicht.

Als Umgehung werden die Ports `23200` - `23299` der CIP Hosts via Webserver getunnelt (`ws` → `wss`).

    # Forward Websocket from CIP for Xpra
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteRule ([0-9][a-z][0-9])([0-9][0-9])    ws://cip$1.cip.cs.fau.de:232$2/ [P,L]



Sicherheit(sbedenken)
---------------------

Für die Xpra-Verbindung wird ein temporäres Passwort (alphanumerisch, 32 Zeichen) verwendet. Der Server beendet sich nach wenigen Sekunden Inaktivität. Das sollte ausreichend sicher sein.

Das Starten der Session via SSH ist eine rein pragmatische Lösung, da bereits in 3 Wochen das Semester beginnt.
Sofern die CIP Admins auf eine besser Lösung kommen, kann diese Lösung verworfen werden -- alternative Ansätze gibt es genug (Extra Dienst zum starten als privilegierter Nutzer mit `--uid` Parameter, Auth direkt an Kerberos, ...), jedoch fehlen uns dazu neben der Zeit auch schlicht die Berechtigung.

Die Verwendung von Xpra selbst über den HTML5 Client (und alles via SSL) dürfte sicher sein.


Lizenz
------

Der HTML5 Client von Xpra wurde unter der **Mozilla Public License Version 2.0 (MPLv2)** veröffentlicht.
Die Anpassungen für den Einsatz im CIP steht unter der **GNU General Public License (GPLv3)**.


Schlusswort
-----------

Für dieses Semester werden die üblichen Regularien außer Kraft gesetzt.
