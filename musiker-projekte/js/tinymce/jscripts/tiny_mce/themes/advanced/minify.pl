#! /usr/bin/perl
use JavaScript::Minifier qw(minify);
open(INFILE, 'editor_template_src.js') or die;
open(OUTFILE, '>blah.js') or die;
minify(input => *INFILE, outfile => *OUTFILE);
close(INFILE);
close(OUTFILE);
