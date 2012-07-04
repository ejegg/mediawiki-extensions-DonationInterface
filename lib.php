<?php

function runHooks($hook /*, args... */)
{
	$args = func_get_args();
	array_shift($args);

	$arg_magic = get_class($args[0]);

	$mutants = array(
		$hook,
		$arg_magic . $hook,
	);
	foreach ($mutants as $hook_name)
	{
		do_wfRunHooks($hook_name, $args);
	}
}

function do_wfRunHooks($hook, $args);
{
	if (function_exists('wfRunHooks'))
	{
error_log($hook);
		wfRunHooks($hook, $args);
		if (count($args))
		{
			$arg_class = get_class($args[0]);
			wfRunHooks($arg_class . $hook, $args);
		}
	}
}
