# Technisch ontwerp quail aansturing

## Eisen

Het client site programma moet van sites een scan kunnen doen met behulp van quail. De url's van de pagina's die gechecked moeten worden
komen uit nutch. In nutch staan heel veel url's. Het script moet een subset hiervan testen. Daarna moet opgeslagen worden welke url's getest zijn.
Verder moet het mogelijk zijn om url's te hertesten.

## Algemene werking van het script.

Het script doet de volgende stappen:

1) Bepaal of er nog nieuwe websites zijn die getest moeten worden
2) Voor nieuwe websites waarvoor nog geen url's zijn opgehaald vragen we aan de nutch solr om er 600 op te halen die we kunnen testen
3) Schrijf de opgehaalde url's in de database.
4) Ga nu voor in de lijst met url's in de database, voor de eerste 100 die nog niet getest zijn de tests uitvoeren. Update de lijst met url's zodat aangegeven wordt dat ze getest zijn.

## Methods in het script

De volgende methods/functions zitten in het script:

    function init() {
      // Maak een global variabele aan voor settings.
    }

    function getDatabaseConnection() {
      // Haal een database connectie object op. Dit wordt PDO
      return PDO
    }

    function updateWebsiteEntries() {
      // Lees een bestandje in van de server.
      // Het bestand heeft 1 website url per line.
      // We lezen het hele bestandje in en maken het vervolgens weer leeg.

      // De url's worden in een table gezet waarin wordt aangegeven dat er opnieuw getest moet worden.
      // Als er al url's in de table

    }

    function performTest(url_id) {
      // Voer de test voor een url_id

      // Schrijf het resultaat in solr.
    }

Voor het aansturen van quail moeter er dingen worden opgeslagen. Hiervoor moet er een database komen.

We hebben nu een zeer eenvoudig script. Dat moet uitgebreid worden met mysql toegang. Daarnaast hebben we iets nodig om snel client site php uit te voeren en op een makkelijke manier de data op te kunnen halen.


## Database tables

Table: websites

- wid (int)
- url
- status:  0: scheduled, 1: testing, 2: tested

Table: url's

- url_id (int)
- wid (referentie naar website table)
- url
- status: 0:untested 1: tested

De database maken we gewoon lokaal aan. De sql exporteren, waardoor we daarvoor geen code nodig hebben.