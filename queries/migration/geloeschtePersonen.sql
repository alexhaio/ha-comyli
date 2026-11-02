SELECT
	`Name`,`Vorname`,`Geburtsdatum`,`Geschlecht`,`Herkunftsland`,
	lastChanged,`lastChangedUser`,`AZLand`,`ZugewiesenAm`,`betreuungEnde`,`betreuungEndeDatum`,`loeschen`,`loeschenGrund`,
	`geloescht`
FROM personen WHERE geloescht=1