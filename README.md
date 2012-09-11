IOSTAT Extended
===============

by Olivier Doucet
version 0.02


This tool can show iostat grouped by DRBD group, or if any, physical device.
(running on Linux only)


Usage
-----

```bash
php iostat.php <seconds>
```

Example
-------
```bash
php iostat.php 10 
```
will run the tool with 10 seconds aggregate.


How it works
------------

This tool is reading /sys/block/xxx/stat files so there is no lock.


Output sample
-------------

<pre>
===============================[2012-09-11 12:34:51]===============================
 riops    wiops      rK/s      wK/s
    65      154      1260       226 /dev/drbd/common_03
     1        1        16         4    common_03/db_vol1
    34        2       844        20    common_03/db_vol2
     0        0         0         0    common_03/db_vol3
    28        1       392         8    common_03/db_vol4
     0       54         0       194    common_03/sys_servers
     0      110         0       295 /dev/drbd/common_04
     0        0         0         0    common_03/block_mail
     0      110         0       295    common_03/fs_mail

</pre>

TODO
----

* Specify physical device / drbd device
* More comments in source :)