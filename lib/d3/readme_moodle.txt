Homepage for D3 is https://d3js.org.

Procedure for including D3 library into Moodle.

* Create a suitable directory.
* Perform the following commands.

$ wget https://registry.npmjs.org/d3/-/d3-7.2.1.tgz
$ tar -xzf d3-7.2.1.tgz
$ cd package
$ cp -r dist LICENSE -t <plugin>/lib/d3

<plugin> is the root directory of this plugin.

