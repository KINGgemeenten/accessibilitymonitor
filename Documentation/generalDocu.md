# General docu

## Killing phantomjs processes older than 15 minutes

Phantomjs processes may be orphaned and hanging for to long. In order to kill them all, use the following command:

    killall --older-than 15m phantomjs