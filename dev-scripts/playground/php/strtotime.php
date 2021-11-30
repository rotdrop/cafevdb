<?php

print_r((new \DateTime(strtotime('2016-02-09')))->setTimezone(new DateTimeZone('CET')));
