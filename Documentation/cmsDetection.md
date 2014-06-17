# Cms detection.

Cms detection can be done using phantalyzer, a script for phantomjs that uses wappalyzer.
The incorporation of this test script is as follows:

- Add the cms detection in the phantomQuailWorker.
- Add the website to the phantomQuailWorker (in the constructor)
- During runtime, add the csm detection as a first step if the website has no cms detected yet.
- Add the cms string as a multivalued field to the url record in solr.
- When the worker is finished, write the cms to the website as well as to the url.