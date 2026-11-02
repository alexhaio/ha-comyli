SELECT
		IF(alteDaten='1',CONCAT('<span class=''notChanged''>',personen.AZLand,'</span>'),personen.AZLand) AS ID,
		personen.Name,
		personen.Vorname,
		
		orte.Adresse AS `PLZ Ort`,
		CONCAT(unterkuenfte.strasse, ' ',unterkuenfte.nr) AS Adresse,
		EinzugAm,
		personen.Kontaktinfo,
		personen.email_ AS email,
		
		unterkuenfte.etage AS Etage,
		unterkuenfte.lage AS Lage,
		unterkuenfte.typ AS Typ,
		
		personen.Zimmer,
		unterkuenfte.betreuerIn AS BetreuerIn,
		personen.Geburtsdatum,
		personen.Geschlecht,
		personen.Herkunftsland,
		personen.Geburtsort, 
		personen.Ausweisablaufdatum,
		personen.Aufenthaltstitel, 
		personen.Arbeitserlaubnis,
		
		ZugewiesenAm,
		personen.Familienstand,
		personen.Religion,
		personen.Sprache,
		personen.Fremdsprache,
		
		keinLeistungsbezug, 
		wohngeld,
		wohngeldAb,
		kinderzuschlag,
		kinderzuschlagAb,
		Asyl,
		AsylAb,
		
		SGBII,
		SGBIIab,
		SGB_XII,
		SGB_XII_ab, 
		
		einstufungstest,
		ergebnisKursart,
		modul,
		`personen-sprachkurse-kurse_hoechstes_ergebnis`.ergebnis,
		
		IF(aktuelleMassnahme <> '',aktuelleMassnahme,'<span class=''noWork''>-- ohne Eintrag --</span>') AS aktuelleMassnahme,
		massnahmeVon,
		massnahmeBis,
		
		Sonstiges,
		WichtigeKontakte,
		ehrenamtlBetreuung,
		ehrenamtlBetreuungTel,
		Familienpos,
		
		personen.basisdatenaenderung,
		personen.lastChanged,
		personen.lastChangedUser
		
		/*CASE
			WHEN personen.betreuungsaufwand = 'Hoch'	THEN CONCAT('<span class=''bg_red''>',personen.betreuungsaufwand,'</span>')
			WHEN personen.betreuungsaufwand = 'Mittel'	THEN CONCAT('<span class=''bg_yellow''>',personen.betreuungsaufwand,'</span>')
			ELSE CONCAT('<span class=''bg_green''>',personen.betreuungsaufwand,'</span>')
		END AS betreuungsaufwand,*/
		
FROM personen
LEFT JOIN unterkuenfte ON personen.unterkunftid=unterkuenfte.id
LEFT JOIN orte ON unterkuenfte.ort=orte.Name
LEFT JOIN (
	SELECT
		AZLand,
		SUM(IF(
			(
				personen.basisdatenaenderung < DATE_SUB(NOW(), INTERVAL 90 DAY)
				AND personen.adminpruefung < DATE_SUB(NOW(), INTERVAL 90 DAY)
			)
			OR personen.lastChanged < DATE_SUB(NOW(), INTERVAL 90 DAY),
			1,0
		)) AS alteDaten
	FROM personen
) AS notChanged ON personen.AZLand = notChanged.AZLand
LEFT JOIN (
	SELECT
		AZLand,
		aktuellsteMassnahme AS aktuelleMassnahme,
		MAX(massnahmeVon) AS massnahmeVon,
		massnahmeBis
	FROM (
			SELECT AZLand, massnahme AS aktuellsteMassnahme, datumVon AS massnahmeVon, datumBis AS massnahmeBis
			FROM personen_massnahmenmanagement
		UNION
			SELECT AZLand, CONCAT('Sprachkurs ',typ,' (',kursname,')'),von,bis 
			FROM `personen-sprachkurse-kurse`
			WHERE ext = 0
	) AS personen_massnahmenmanagement_und_sprachkurse
	WHERE massnahmeBis >= NOW() /*OR massnahmeBis = '0000-00-00'*/
	GROUP BY AZLand
) AS personen_massnahmenmanagement ON personen.AZLand = personen_massnahmenmanagement.AZLand
LEFT JOIN (
	SELECT AZLand, einstufungstest, ergebnisKursart, modul
	FROM `personen-sprachkurse`
	WHERE ext = 0
	GROUP BY AZLand
) AS `personen-sprachkurse` ON personen.AZLand = `personen-sprachkurse`.AZLand
LEFT JOIN (
	SELECT AZLand, ergebnis, MAX(wert) AS wert
	FROM `personen-sprachkurse-kurse`
	LEFT JOIN personen_sprachkurse_ergebnisse ON `personen-sprachkurse-kurse`.ergebnis = personen_sprachkurse_ergebnisse.name
	WHERE ext = 0
	GROUP BY AZLand
) AS `personen-sprachkurse-kurse_hoechstes_ergebnis` ON personen.AZLand = `personen-sprachkurse-kurse_hoechstes_ergebnis`.AZLand
WHERE geloescht = 0
