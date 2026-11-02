SELECT
	ID AS 'ID/AZLand',
	PkzBAMF,
	BerNr,
	Name,
	Vorname,
	Geburtsdatum,
	StrasseNr,
	Adresse,
	/*GROUP_CONCAT(CONCAT(typ,':',naechsteStufeEmpfohlen)) AS Flags,*/
	IF(INSTR(GROUP_CONCAT(CONCAT(typ,':',naechsteStufeEmpfohlen)),'Landkreissprachkurs:')>0,'Ja','Nein') AS Landkreissprachkurs,
	IF(INSTR(GROUP_CONCAT(CONCAT(typ,':',naechsteStufeEmpfohlen)),'A1:')>0,'Ja','Nein') AS A1,
	IF(INSTR(GROUP_CONCAT(CONCAT(typ,':',naechsteStufeEmpfohlen)),'A2:')>0,'Ja','Nein') AS A2,
	IF(INSTR(GROUP_CONCAT(CONCAT(typ,':',naechsteStufeEmpfohlen)),'A2:1')>0,'Ja','Nein') AS A2naechsteStufe,
	IF(INSTR(GROUP_CONCAT(CONCAT(typ,':',naechsteStufeEmpfohlen)),'B1:')>0,'Ja','Nein') AS B1,
	IF(INSTR(GROUP_CONCAT(CONCAT(typ,':',naechsteStufeEmpfohlen)),'B1:1')>0,'Ja','Nein') AS B1naechsteStufe,
	IF(INSTR(GROUP_CONCAT(CONCAT(typ,':',naechsteStufeEmpfohlen)),'B1 Wiederholer:')>0,'Ja','Nein') AS B1W,
	IF(INSTR(GROUP_CONCAT(CONCAT(typ,':',naechsteStufeEmpfohlen)),'B1 Wiederholer:1')>0,'Ja','Nein') AS B1WnaechsteStufe,
	IF(INSTR(GROUP_CONCAT(CONCAT(typ,':',naechsteStufeEmpfohlen)),'B2:')>0,'Ja','Nein') AS B2,
	IF(INSTR(GROUP_CONCAT(CONCAT(typ,':',naechsteStufeEmpfohlen)),'B2:1')>0,'Ja','Nein') AS B2naechsteStufe,
	IF(INSTR(GROUP_CONCAT(CONCAT(typ,':',naechsteStufeEmpfohlen)),'C1:')>0,'Ja','Nein') AS C1,
	IF(INSTR(GROUP_CONCAT(CONCAT(typ,':',naechsteStufeEmpfohlen)),'C1:1')>0,'Ja','Nein') AS C1naechsteStufe,
	IF(INSTR(GROUP_CONCAT(CONCAT(typ,':',naechsteStufeEmpfohlen)),'C2:')>0,'Ja','Nein') AS C2,
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
			`personen-sprachkurse-kurse-fehlen`.infoBetreuerBemerkungKF
		FROM `personen-sprachkurse-personen`
		LEFT JOIN `personen-sprachkurse` ON ID=`personen-sprachkurse`.AZLand AND `personen-sprachkurse`.ext=1
		LEFT JOIN `personen-sprachkurse-kurse` ON ID=`personen-sprachkurse-kurse`.AZLand AND `personen-sprachkurse-kurse`.ext=1
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
			`personen-sprachkurse-kurse-fehlen`.infoBetreuerBemerkungKF
		FROM personen
		LEFT JOIN unterkuenfte ON unterkuenfte.id=personen.unterkunftid
		LEFT JOIN orte ON unterkuenfte.ort = orte.Name
		LEFT JOIN `personen-sprachkurse` ON personen.AZLand=`personen-sprachkurse`.AZLand AND `personen-sprachkurse`.ext=0
		LEFT JOIN `personen-sprachkurse-kurse` ON personen.AZLand=`personen-sprachkurse-kurse`.AZLand AND `personen-sprachkurse-kurse`.ext=0
		LEFT JOIN `personen-sprachkurse-kurse-fehlen` ON personen.AZLand=`personen-sprachkurse-kurse-fehlen`.AZLand AND `personen-sprachkurse-kurse-fehlen`.ext=0
		WHERE geloescht = 0
			AND PkzBAMF IS NOT NULL
	/*
	`personen-sprachkurse-personen`/personen ->	`personen-sprachkurse`	-> `personen-sprachkurse-kurse` + `personen-sprachkurse-kurse-fehlen`
																		-> `personen-sprachkurse-kurse` + `personen-sprachkurse-kurse-fehlen`
												`personen-sprachkurse`	-> `personen-sprachkurse-kurse` + `personen-sprachkurse-kurse-fehlen`
																		-> `personen-sprachkurse-kurse` + `personen-sprachkurse-kurse-fehlen`
	*/
) AS dat
WHERE
	typ IS NOT NULL
GROUP BY ID
ORDER BY Name
