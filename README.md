IOSTAT Extended
===============

by Olivier Doucet
version 0.02


This tool can show iostat grouped by DRBD group, or if any, physical device.
(running on Linux only)


Usage
-----

php iostat.php <seconds>


Example
-------

php iostat.php 10 
will run the tool with 10 seconds aggregate.


How it works
------------

This tool is reading /sys/block/xxx/stat files so there is no lock.


Output sample
-------------

===============================[2012-09-11 12:34:51]===============================
                                                  riops    wiops      rK/s      wK/s
/dev/drbd/common_03                                 10      177        48       879
   common_03/db_vol1                                 0        1         0        32
   common_03/db_vol2                                 2        1        16        20
   common_03/db_vol3                                 0        0         0         0
   common_03/db_vol4                                 0        1         0         4
   common_03/db_vol5                                 1        1         4         8
   common_03/db_vol6                                 7      113        28       395
   common_03/db_vol7                                 0       60         0       420
/dev/drbd/common_04                                  0      116         0       278
   common_03/block_mail                              0       22         0        22
   common_03/fs_mail                                 0       94         0       256


TODO
----

Specify physical device / drbd device
More comments in source :)
Add colors