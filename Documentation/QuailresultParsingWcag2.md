# Quail result parsing

Results that stem from quail have a raw result. We want to parse the data, so we can easily save the main results,
so they can be further processed.

## Main result

The main result, which we need for further processing is per criterium. The most important information is the outcome.
The result for a criterium has also a haspart array, which also contains all cases. These cases will not be stored in the database.
Therefor, the haspart will be deleted from the stdClass.
Also for further analysis, the criterium number should be added.

So the following steps are needed:

1. extract the criterium number from the property testRequirement.
2. Key the result array by criterium number.
3. remove the hasPart

## Cases

Each result has cases.