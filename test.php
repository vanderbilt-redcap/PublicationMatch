<?php
if(!defined("SUPER_USER") || !SUPER_USER) {
	die();
}


$module->runProjectCron($project_id);

echo "Completed running update for pid $project_id";