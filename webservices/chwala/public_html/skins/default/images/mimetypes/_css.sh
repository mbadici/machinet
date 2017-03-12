#!/usr/bin/perl

opendir(DIR, '.');
@FILES = readdir(DIR);

open(FILE, '>style.css');

$x = 0;
while ($FILES[$x]) {
    my $file = $FILES[$x];
    my $class = $file;
    my $line = '';

    if ($file eq '.' || $file eq '..') {
        $x++;
        next;
    }

    $class =~ s/\.png$//;
    $class =~ s/[^a-z0-9_]/_/g;

    $line .= "#filelist tbody td.filename." .$class . " span {\n";
    $line .= "  background: url($file) 0 0 no-repeat;\n";
    $line .= "}\n";

    print FILE $line;
    $x++;
}
