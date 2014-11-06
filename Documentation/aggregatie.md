# Aggregatie naar criterium

Om te aggregeren naar criterium worden de volgende regels opgesteld:

- Als een criterium bij 10% van de pagina's die niet op inapplicable stonden een failed resultaat heeft krijgt is het geagregeerde antwoord van het criterium 'failed'.
- Anders als een criterium cantTell op één of meerdere pagina's als antwoord heeft is het geagregeerde antwoord van het criterium 'cantTell'
- Anders als een criterium passed op één of meerdere pagina's als antwoord heeft en als minder dan 10% procent van de pagina's failed als antwoord heeft is het geagregeerde antwoord van het criterium 'passed'
- Anders als een criterium inapplicable op één of meerdere pagina's als antwoord heeft is het geagregeerde antwoord van het criterium 'inapplicable'
- Anders is het geagregeerde antwoord van het criterium 'untested'


## Voorbeelden

Bij de website van gemeente A zijn 100 pagina's getest met een automatisch onderzoek. Daar zijn de volgende resultaten uitgekomen.

    1.1.1 - failed: 23, cantTell 77
    1.2.2 - cantTell: 1, passed: 4, inapplicable: 95
    1.3.1 - failed: 15, cantTell 85
    1.4.1 - cantTell: 100
    1.4.3 - failed: 5, passed: 95
    2.4.4 - failed: 10, passed: 90
    2.4.7 - cantTell: 100
    3.1.1 - cantTell: 100
    4.1.1 - failed: 20, passed: 80
    4.1.2 - failed: 40, cantTell: 60

De eerste stap in de aggregatie is om voor ieder succescriterium één antwoord te vinden. Hiervoor gebruiken we de volgende regels:
- Als een criterium bij 10% van de pagina's die niet op inapplicable stonden een failed resultaat heeft krijgt is het geagregeerde antwrood van het criterium 'failed'.
- Anders als een criterium cantTell op één of meerdere pagina's als antwoord heeft is het geagregeerde antwoord van het criterium 'cantTell'
- Anders als een criterium passed op één of meerdere pagina's als antwoord heeft is het geagregeerde antwrood van het criterium 'passed'
- Anders als een criterium inapplicable op één of meerdere pagina's als antwoord heeft is het geagregeerde antwrood van het criterium 'inapplicable'
- Anders is het geagregeerde antwrood van het criterium 'untested'

Om niet de indruk van hoge mate van nauwkeurigheid te geven (die we niet hebben) geven we antwoorden weer in termen van een ratio tussen 1 en 10:

Voor Gemeente Ab wordt dat dan:

    1.1.1 - failed          2:10    (23%)
    1.2.2 - cantTell        2:10    (20% =1/(1+4) )
    1.3.1 - failed          1:10    (15%)
    1.4.1 - cantTell        10:10   (100%)
    1.4.3 - passed          meer dan 9:10 (95%)
    2.4.4 - failed          1:10    (10%)
    2.4.7 - cantTell        10:10   (100%)
    3.1.1 - cantTell        10:10   (100%)
    4.1.1 - failed          2:10    (20%)
    4.1.2 - failed          4:10    (40%)