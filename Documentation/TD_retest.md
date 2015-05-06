RE-TEST FUNCTIONALITY
=====================
URLs that failed testing once must be re-tested after certain periods of time.
- Add a testing status that indicates a URL must be re-tested.
- Extend URLs with a field that contains the number of failed test runs.
- Build a console command that reschedules URLs based using waiting time configuration stored in a container parameter.
- Extend the daemon with a call to this command so the process is executed automatically.
