<?php

function runHooks($hook, $args)
{
	$arg_magic = get_class($args[0]);

	$mutants = array(
		$hook,
		$arg_magic . $hook,
	);
	foreach ($mutants as $hook_name)
	{
		if (function_exists('wfRunHooks'))
			wfRunHooks($hook_name, $args);
	}
}
