# Unique Source errors

It should be possible for the frontend to display unique errors.
The basic way to do this, is to collect cases per criterium and sending only the first case of a special kind to Solr.
The structure in which quail sends back the error is as follows:

    hasPart
      0
        testCase = 'test id'
        outcome
          result = [earl result]
          info
            en = [English description]
            nl = [dutch description]
        type = 'assertion'
        pointer
          0
            type = 'CharSnippetCompoundPointer'
            chars = [code snippet]
            CSSSelector = [CSS selector]

We need to create a property on which Solr can group the results. The most simple way to do this is to use the testCase
as a test id. 

We don't want to send each case to Solr, because that would be to heavy for Solr.
For each url we store for each criterium only one test case of a certain type that is failed.
In the backend, we key the cases by [criterium]_[testCase] and only add the case if it is not already set.

## Solr storing

The existing schema for solr is not descriptive enough anymore using the new version of quail. 
For the errors we therefor create a new schema.xml: version 3.

The schema will consist of the following fields:

    id: unique id
    url: full url
    url_id: an escaped url for deleting. For now we leave this in order to gain speed.
    url_main: main url (for instance amsterdam.nl)
    url_sub: complete domain including subdomain (for instance formulieren.amsterdam.nl)
    element: html snippet of the element
    name_en: english description of the test case (for instance 'Adjacent links that point to the same location should be merged')
    name_nl: dutch description of the test case
    succescriterium: former applicationframework.
    test_result: an earl compliant test result. Will now only contain failed.
    document_type: the document type (will always be case)
    
    
The following fields from the old schema will be deleted (or clearly separated in the schema.xml that they are not used):
    
   content: serialized content of the result. This is too big, so won't be stored.
   name: description of the test case. Will be replaced by name_en and name_nl
   applicationframework: is replaced by succescriterium.
   techniques: techniques is not in the results anymore
   technologies: technologies used (like apache, drupal, php)
   tags: not available anymore int the quail result.
   testibility: will always be 1, because otherwise the case is not written
   testtype: was always custom
   severity: not available anymore in the final result of quail. 