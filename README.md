<h2>Allgemeines</h2>
Dieser LBS liest Werte einer Viessmann-Heizung über den vcontrold-Dienst und den vclient aus. Der Sourcecode stammt aus dem openv-Wiki und wurde für CentOS6.5 kompiliert. Es muss sichergestellt sein, dass der vcontrol-Dienst gestartet ist.

Der Aufruf erfolgt für jede Adresse separat. Achtung, die Anfragen benötigen aufgrund des Übertragungsprotokolls einige Zeit. Es sollte also bei mehreren Abfragen eine entsprechende Pause eingebaut werden.

<h2>Changelog</h2>
<ul>
	<li>v0.1: Initial version</li>
	<li>v0.2: für CentOS kompilierte vcontrold und vclient in Archiv beigefügt</li>
	<li>v0.3: Ein- und Ausgang für JSON-Strings hinzugefügt</li>
	<li>v0.4: Pausen und Wiederholungen eingefügt, da es bei Verwendung des Protokoll P300 zu Lesefehlern kommt, wenn Anfragen zu schnell hintereinander ausgeführt werden</li>
</ul>