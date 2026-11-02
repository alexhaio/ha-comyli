SELECT
	ID AS 'ID/AZLand',
	PkzBAMF,
	BerNr,
	Name,
	Vorname,
	Geburtsdatum,
	StrasseNr,
	Adresse,
	berechtigung,
	verpflichtung,
	CONCAT('<a href=''./?f=sprachkurse&amp;AZLand=',ID,'&amp;ext=1'' target=''_blank''>Zum Sprachkurs</a>') AS Spachkurse
FROM (
		SELECT
			`personen-sprachkurse-personen`.ID,
			`personen-sprachkurse-personen`.Name,
			`personen-sprachkurse-personen`.Vorname,
			`personen-sprachkurse-personen`.Geburtsdatum,
			`personen-sprachkurse-personen`.StrasseNr,
			CONCAT(`personen-sprachkurse-personen`.PLZ,' ',`personen-sprachkurse-personen`.Wohnort) AS Adresse,
			`personen-sprachkurse`.*,
			`personen-sprachkurse-kurse-fehlen`.vonKF,
			`personen-sprachkurse-kurse-fehlen`.bisKF,
			`personen-sprachkurse-kurse-fehlen`.infoBetreuerDatumKF,
			`personen-sprachkurse-kurse-fehlen`.infoBetreuerBemerkungKF
		FROM `personen-sprachkurse-personen`
		LEFT JOIN `personen-sprachkurse` ON ID=`personen-sprachkurse`.AZLand AND `personen-sprachkurse`.ext=1
		LEFT JOIN `personen-sprachkurse-kurse-fehlen` ON ID=`personen-sprachkurse-kurse-fehlen`.AZLand AND `personen-sprachkurse-kurse-fehlen`.ext=1
		WHERE PkzBAMF IS NOT NULL
	UNION
		SELECT
			personen.AZLand,
			personen.Name,
			personen.Vorname,
			personen.Geburtsdatum,
			CONCAT(strasse, ' ', nr, ' ', etage, ' ', lage),
			orte.Adresse,
			`personen-sprachkurse`.*,
			`personen-sprachkurse-kurse-fehlen`.vonKF,
			`personen-sprachkurse-kurse-fehlen`.bisKF,
			`personen-sprachkurse-kurse-fehlen`.infoBetreuerDatumKF,
			`personen-sprachkurse-kurse-fehlen`.infoBetreuerBemerkungKF
		FROM personen
		LEFT JOIN unterkuenfte ON unterkuenfte.id=personen.unterkunftid
		LEFT JOIN orte ON unterkuenfte.ort = orte.Name
		LEFT JOIN `personen-sprachkurse` ON personen.AZLand=`personen-sprachkurse`.AZLand AND `personen-sprachkurse`.ext=0
		LEFT JOIN `personen-sprachkurse-kurse-fehlen` ON personen.AZLand=`personen-sprachkurse-kurse-fehlen`.AZLand AND `personen-sprachkurse-kurse-fehlen`.ext=0
		WHERE geloescht = 0
			AND PkzBAMF IS NOT NULL
	/*
	`personen-sprachkurse-personen`/personen ->	`personen-sprachkurse`	-> `personen-sprachkurse-kurse-fehlen`
																		-> `personen-sprachkurse-kurse-fehlen`
												`personen-sprachkurse`	-> `personen-sprachkurse-kurse-fehlen`
																		-> `personen-sprachkurse-kurse-fehlen`
	*/
) AS dat
WHERE
	(
		ergebnisKursart = 'Allgemein'
		OR ergebnisKursart = 'Alpha'
		OR ergebnisKursart = 'Jugend'
	)
	AND `BerNr` = ''			/*BerNr ist leer*/
	AND `einstufungstest` = ''	/*einstufungstest ist leer*/
	AND `modul` = ''			/*...*/
	AND `bemerkung` = ''
	AND `nichtTeilgenommen` = ''
	AND `infoBetreuerDatum` = ''
	AND `infoBetreuerBemerkung` = ''
	AND `EGVB1` = ''
	AND `akteFMzugefuehrt` = ''
	AND `Ablage` = ''
	AND `wiedervorlage` = ''
ORDER BY Name
