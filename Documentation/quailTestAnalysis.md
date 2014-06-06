# Quail test analysis.

The results of quail must be analyzed. The following analysis should be done:

1. Errors, passes and not applicable should be send to solr for displaying purposes.
2. Aggregated results per url

## Raw data

The raw test data contains the following information:

    - id: the id of the test
    - title: the title of the test
    - description: the description of the test
    - type: the type of the test
    - testability: the testability
    - guidelines: the guidelines (and techniques for this test)
    - tags: sort of taxonomy
    - cases: an array of test results, each consisting of:
      - status: passed, failed, not applicable
      - selector: the jquery selector for the element.

We can further extract those data the following way:

- Explode by casus: explode the documents to casus, so these can be indexed later.
- Order by wcag: order the cases bij wcag

## Errors, passes and other

Raw results from quail should be send to Solr.

    - url: complete url
    - element: html of the element
    - name:
    - wcag: json containing wcag elements
    - tags (array): taxonomy
    - testability
    - testtype
    - severity
    - url_main
    - url_sub
    - url_id: altered string of url used for deleting
    - id: id in solr
    - applicationframework (array): wcag number
    - techniques (array): technique number
    - test_result

## Aggregated result per ulr.

Per url we want to aggregate the results as well. The results will have the following format:

    - url
    - wcag resulsts (array)
      - wcag number
        - amount passes:
        - amount fails
        - amount cannot tell
        - amount inapplicable


# Current situation

In the current situation, solr expects to see the following results:

    - url: complete url
    - element: html of the element
    - name:
    - wcag: json containing wcag elements
    - tags (array): taxonomy
    - testability
    - testtype
    - severity
    - url_main
    - url_sub
    - url_id: altered string of url used for deleting
    - id: id in solr
    - applicationframework (array): wcag number
    - techniques (array): technique number