SELECT COUNT(Name) AS Personen, WEEK(Umzug) AS Kalenderwoche, Umzugsart
FROM `umzuege`
WHERE Umzug > '2016-05-15'
GROUP BY WEEK(Umzug), umzugsart
ORDER BY Kalenderwoche