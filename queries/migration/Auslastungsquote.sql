	SELECT
		ort,
		strasse,
		nr,
		etage,
		lage,
		IF(wohnTyp='Renovierung',CONCAT('<span style=''color:red''>',wohnTyp,'</span>'),wohnTyp) AS wohnTyp,
		SUM(MaxBelegung) AS TatsächlicheBelegung,
		IF(
			SUM(moeglBelegung)>SUM(MaxBelegung),
			CONCAT('<span style=''color:red''>',SUM(moeglBelegung),'</span>'),
			IF(
				SUM(moeglBelegung)<SUM(MaxBelegung),
				CONCAT('<span style=''color:green''>',SUM(moeglBelegung),'</span>'),
				SUM(moeglBelegung)
			)
		) AS MöglicheKapazität,
		SUM(FreiePlaetze) AS FreiePlaetze,
		CONCAT(
			'<span ',
			IF(SUM(MaxBelegung)=0,'style=''color:red''>&#x21AF;',
				IF((SUM(MaxBelegung)-SUM(FreiePlaetze))/SUM(MaxBelegung) >= 0.9 OR SUM(FreiePlaetze)=0, 'style=''color:green''>&#x263B;',
					IF((SUM(MaxBelegung)-SUM(FreiePlaetze))/SUM(MaxBelegung) > 0.5, 'style=''color:yellow''>&#x263B;',
						'style=''color:red''>&#x263B;'
					)
				)
			),
			'</span>'
		) AS Status,
		Religion,
		Herkunftsland
	FROM [QRY]kontingenteZimmer[/QRY]
	WHERE
		typ <> 'privat'
		AND typ <> 'Ukraine'
		AND wohnTyp <> 'Gekündigt'
	GROUP BY id
UNION
	
	SELECT
		'Summe', '', '', '', '', typ,
		SUM(MaxBelegung) AS TatsächlicheBelegung,
		IF(
			SUM(moeglBelegung)>SUM(MaxBelegung),
			SUM(moeglBelegung),
			IF(
				SUM(moeglBelegung)<SUM(MaxBelegung),
				SUM(moeglBelegung),
				SUM(moeglBelegung)
			)
		) AS MöglicheKapazität,
		SUM(FreiePlaetze) AS FreiePlaetze,
		CONCAT(((SUM(MaxBelegung)-SUM(FreiePlaetze))/SUM(MaxBelegung))*100,'%') AS Quote,
		'',''
	FROM [QRY]kontingenteZimmer[/QRY]
	WHERE
		typ <> 'privat'
		AND typ <> 'Ukraine'
		AND wohnTyp <> 'Gekündigt'
	GROUP BY typ
	
UNION
	
	SELECT
		'Alle', '', '', '', '', '',
		SUM(MaxBelegung) AS TatsächlicheBelegung,
		IF(
			SUM(moeglBelegung)>SUM(MaxBelegung),
			SUM(moeglBelegung),
			IF(
				SUM(moeglBelegung)<SUM(MaxBelegung),
				SUM(moeglBelegung),
				SUM(moeglBelegung)
			)
		) AS MöglicheKapazität,
		SUM(FreiePlaetze) AS FreiePlaetze,
		CONCAT(((SUM(MaxBelegung)-SUM(FreiePlaetze))/SUM(MaxBelegung))*100,'%') AS Quote,
		'',''
	FROM [QRY]kontingenteZimmer[/QRY]
	WHERE
		typ <> 'privat'
		AND typ <> 'Ukraine'
		AND wohnTyp <> 'Gekündigt'	