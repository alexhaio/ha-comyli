	SELECT
			`personen-sprachkurse-personen`.ID,
			`personen-sprachkurse-personen`.Name,
			`personen-sprachkurse-personen`.Vorname,
			`personen-sprachkurse-personen`.Geburtsdatum,
			'' AS SGBII,
			`personen-sprachkurse`.telefon AS Kontaktinfo,
			IF(`personen-sprachkurse-personen`.Geschlecht = 'weiblich','Frau','Herr') AS Anrede,
			`personen-sprachkurse-personen`.StrasseNr,
			CONCAT(`personen-sprachkurse-personen`.PLZ,' ',`personen-sprachkurse-personen`.Wohnort) AS Adresse,
			`personen-sprachkurse`.*,
			`personen-sprachkurse-kurse`.typ,
			`personen-sprachkurse-kurse`.von,
			`personen-sprachkurse-kurse`.bis,
			`personen-sprachkurse-kurse`.bemerkungK,
			`personen-sprachkurse-kurse`.pdf,
			`personen-sprachkurse-kurse`.naechsteStufeEmpfohlen,
			`personen-sprachkurse-kurse`.kursartK,
			`personen-sprachkurse-kurse`.bildungstraeger,
			`personen-sprachkurse-kurse-fehlen`.vonKF,
			`personen-sprachkurse-kurse-fehlen`.bisKF,
			`personen-sprachkurse-kurse-fehlen`.infoBetreuerDatumKF,
			`personen-sprachkurse-kurse-fehlen`.infoBetreuerBemerkungKF,
			CONCAT('<a href=''./?f=sprachkurse&amp;AZLand=',`personen-sprachkurse`.AZLand,'&amp;ext=',`personen-sprachkurse`.ext,''' target=''_blank''>Zum Sprachkurs</a>') AS Sprachkurse
		FROM `personen-sprachkurse-personen`
		LEFT JOIN `personen-sprachkurse` ON ID=`personen-sprachkurse`.AZLand AND `personen-sprachkurse`.ext=1
		LEFT JOIN `personen-sprachkurse-kurse` ON ID=`personen-sprachkurse-kurse`.AZLand AND `personen-sprachkurse-kurse`.ext=1
		LEFT JOIN `personen-sprachkurse-kurse-fehlen` ON ID=`personen-sprachkurse-kurse-fehlen`.AZLand AND `personen-sprachkurse-kurse-fehlen`.ext=1
		WHERE PkzBAMF IS NOT NULL
	UNION ALL
		SELECT
			personen.AZLand,
			personen.Name,
			personen.Vorname,
			personen.Geburtsdatum,
			personen.SGBII,
			personen.Kontaktinfo,
			IF(personen.Geschlecht = 'weiblich','Frau','Herr') AS Anrede,
			CONCAT(strasse, ' ', nr, ' ', etage, ' ', lage),
			orte.Adresse,
			`personen-sprachkurse`.*,
			`personen-sprachkurse-kurse`.typ,
			`personen-sprachkurse-kurse`.von,
			`personen-sprachkurse-kurse`.bis,
			`personen-sprachkurse-kurse`.bemerkungK,
			`personen-sprachkurse-kurse`.pdf,
			`personen-sprachkurse-kurse`.naechsteStufeEmpfohlen,
			`personen-sprachkurse-kurse`.kursartK,
			`personen-sprachkurse-kurse`.bildungstraeger,
			`personen-sprachkurse-kurse-fehlen`.vonKF,
			`personen-sprachkurse-kurse-fehlen`.bisKF,
			`personen-sprachkurse-kurse-fehlen`.infoBetreuerDatumKF,
			`personen-sprachkurse-kurse-fehlen`.infoBetreuerBemerkungKF,
			CONCAT('<a href=''./?f=sprachkurse&amp;AZLand=',`personen-sprachkurse`.AZLand,'&amp;ext=',`personen-sprachkurse`.ext,''' target=''_blank''>Zum Sprachkurs</a>')
		FROM personen
		LEFT JOIN unterkuenfte ON unterkuenfte.id=personen.unterkunftid
		LEFT JOIN orte ON unterkuenfte.ort = orte.Name
		LEFT JOIN `personen-sprachkurse` ON personen.AZLand=`personen-sprachkurse`.AZLand AND `personen-sprachkurse`.ext=0
		LEFT JOIN `personen-sprachkurse-kurse` ON personen.AZLand=`personen-sprachkurse-kurse`.AZLand AND `personen-sprachkurse-kurse`.ext=0
		LEFT JOIN `personen-sprachkurse-kurse-fehlen` ON personen.AZLand=`personen-sprachkurse-kurse-fehlen`.AZLand AND `personen-sprachkurse-kurse-fehlen`.ext=0
		LEFT JOIN `personen-sprachkurse-ext-adressen` ON personen.AZLand=`personen-sprachkurse-ext-adressen`.ID AND `personen-sprachkurse`.ext=0
		WHERE
			(
				geloescht = 0
				OR `personen-sprachkurse-ext-adressen`.ID <> ''
			)
			AND PkzBAMF IS NOT NULL
	/*
	`personen-sprachkurse-personen`/personen ->	`personen-sprachkurse`	-> `personen-sprachkurse-kurse` + `personen-sprachkurse-kurse-fehlen`
																		-> `personen-sprachkurse-kurse` + `personen-sprachkurse-kurse-fehlen`
												`personen-sprachkurse`	-> `personen-sprachkurse-kurse` + `personen-sprachkurse-kurse-fehlen`
																		-> `personen-sprachkurse-kurse` + `personen-sprachkurse-kurse-fehlen`
	*/