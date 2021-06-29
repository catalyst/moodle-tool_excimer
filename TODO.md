# TODO

[NC 2021-06-28] 

This is a simple MVP that logs all calls and stores profiles when execution
time exceeds a trigger given in admin settings adding similar fields and checks
should be pretty simple. Next steps:

- To find bottlenecks, the flame graphs for the individual profiling should
show time spent in each function, not just the total calls made to a function.

- Need sorting and filtering for profile table on homepage: I've omitted a
basic tablelib implementation here as I haven't gotten it right yet.

