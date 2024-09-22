<?php

use WPUmbrella\Actions\Queue\Scheduler\RunManualBackupTask;
use WPUmbrella\Actions\Queue\Scheduler\DeleteBackupTask;
use WPUmbrella\Actions\Queue\Scheduler\StopManualBackupTask;
use WPUmbrella\Core\Constants\BackupStatus;
use WPUmbrella\Core\Constants\BackupTaskStatus;
use WPUmbrella\Core\Constants\BackupTaskType;
use WPUmbrella\Core\Constants\LogCode;
use WPUmbrella\Services\Repository\BackupRepository;
use WPUmbrella\Services\Repository\LogRepository;
use WPUmbrella\Services\Repository\TaskBackupRepository;

/** @var TaskBackupRepository $backupTaskRepository */
$backupTaskRepository = wp_umbrella_get_service('TaskBackupRepository');

/** @var LogRepository $logRepository */
$logRepository = wp_umbrella_get_service('LogRepository');

/** @var BackupRepository $backupRepository */
$backupRepository = wp_umbrella_get_service('BackupRepository');
$backups = [];
$tasks = [];
$logs = [];
$backupId = null;

global $wp;

$backups = $backupRepository->getBackups([
	'limit' => $_GET['limit'] ?? 25,
	'offset' => $_GET['offset'] ?? 0,
]);

$currentUrl = $_SERVER['REQUEST_URI'];

if( array_key_exists('backupId', $_GET) ) {
	$backupId = intval(sanitize_text_field($_GET['backupId']));
	$tasks = $backupTaskRepository->getTasksByBackupId($backupId);
	$logs = $logRepository->getLogsByBackupId($backupId);
}

?>


<div id="wrap-wphealth">
	<div class="wrap">
		<div class="module-wp-health">
			<h1>WP Umbrella - Logs</h1>
			<!-- Separator -->
			<div class="relative">
				<div class="absolute inset-0 flex items-center" aria-hidden="true">
					<div class="w-full border-t border-gray-300"></div>
				</div>
			</div>

			<h2 class="font-bold text-lg mb-2 mt-6">Backup</h2>

			<div class="min-w-full divide-y divide-gray-300">
				<div class="flex gap-4">
					<div scope="col" class="w-5"></div>
					<div scope="col" class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900 sm:pl-0 ml-2">ID</div>
					<div scope="col" class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900">Title</div>
					<div scope="col" class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900 ml-auto">Actions</div>
				</div>
				<div class="divide-y divide-gray-200">

					<?php foreach($backups as $backup):
						$firstTask = $backupTaskRepository->getFirstTaskByBackupId($backup->getId());

						?>
						<div class="flex items-center px-2 <?php echo ($backup->getStatus() === BackupStatus::ERROR) ? 'bg-red-50' : '' ?> <?php echo ($backup->getId() == $backupId) ? 'bg-white' : '' ?>">
							<?php switch($backup->getStatus()) :
								case BackupStatus::ERROR : ?>
									<svg  xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-red-600">
										<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
									</svg>
									<?php break;
								case BackupStatus::FINISHED : ?>
									<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-green-600">
										<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
									</svg>
									<?php break;
								case BackupStatus::IN_PROGRESS : ?>
									<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-orange-600">
										<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
									</svg>
								<?php break;
								default: ?>
									<div class="w-5 h-5 ml-2"></div>
							<?php endswitch; ?>

							<div class="whitespace-nowrap py-2 pl-4 pr-3 text-sm text-gray-500 sm:pl-0">#<?php echo $backup->getId(); ?></div>
							<div class="whitespace-nowrap px-2 py-2 text-sm font-medium text-gray-900">
								<?php echo $backup->getTitle(); ?>
								<?php if($backup->isScheduled()): ?>
									<span class="text-xs bg-white rounded-full p-1 ml-4">Scheduled</span>
								<?php else: ?>
									<span class="text-xs bg-white rounded-full p-1 ml-4">Manual</span>
								<?php endif; ?>

								<span class="text-xs bg-white rounded-full py-1 px-2 ml-4">
									<?php if(!is_null($firstTask)): ?>
									First task: <strong><?php echo is_null($firstTask->getDateSchedule()) ? 'NULL' : $firstTask->getDateSchedule()->format('Y-m-d H:i:s'); ?></strong>
									<?php endif; ?>
								</span>
							</div>
							<div class="whitespace-nowrap px-2 py-2 text-sm text-gray-900 flex gap-4 ml-auto">
								<?php if($backup->getId() == $backupId): ?>
									<form action="<?php echo admin_url('admin-post.php'); ?>">
										<input type="hidden" name="backup_id" value="<?php echo $backupId; ?>" />
										<input type="hidden" name="action" value="<?php echo StopManualBackupTask::ACTION; ?>" />
										<?php wp_nonce_field(StopManualBackupTask::ACTION, StopManualBackupTask::NONCE); ?>
										<button class="inline-flex items-center gap-2 rounded border-0 bg-red-200 py-1.5 px-3 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-gray-600 sm:text-sm sm:leading-6" type="submit">
											<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
												<path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 017.5 5.25h9a2.25 2.25 0 012.25 2.25v9a2.25 2.25 0 01-2.25 2.25h-9a2.25 2.25 0 01-2.25-2.25v-9z" />
											</svg>


											Stop Backup
										</button>
									</form>
									<a class="inline-flex items-center gap-2 rounded border-0 bg-white py-1.5 px-3 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-gray-600 sm:text-sm sm:leading-6 relative" href="<?php echo remove_query_arg('backupId', $currentUrl); ?>">
										<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
											<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
										</svg>

										Hide details
									</a>
								<?php else: ?>
									<a class="inline-flex items-center gap-2 rounded border-0 bg-white py-1.5 px-3 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-gray-600 sm:text-sm sm:leading-6 relative" href="<?php echo add_query_arg('backupId', $backup->getId(), $currentUrl); ?>">
										<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
											<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
											<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
										</svg>

										View details
									</a>
								<?php endif; ?>
							</div>
						</div>

						<div class="bg-white">
							<?php if( ! is_null($backupId) && $backup->getId() == $backupId ) : ?>
								<div class="py-4 px-11 bg-white">
									<strong>PHP Time Limit:</strong> <?php echo @ini_get('max_execution_time'); ?>
									<br />
									<strong>PHP Memory Limit:</strong> <?php echo @ini_get('memory_limit'); ?>
								</div>
								<div class="flex py-4 px-11 bg-white divide-x">
									<div class="w-full pr-4">
										<?php
											$backupConfig =  $backup->getConfigDatabase();

										if(isset($backupConfig['batch']['iterator_position'])):
											$iterator =  $backupConfig['batch']['iterator_position'];
											?>
											<strong>Table Name:</strong> <?php echo isset($backupConfig['tables'][$iterator]) ? $backupConfig['tables'][$iterator]['name'] : 'No table'; ?>
											<br />
											<strong>Iterator:</strong> <?php echo $iterator; ?>
											<br />
											<strong>Part:</strong> <?php echo $backupConfig['batch']['part']; ?>
											<br />
											<strong>Total Tables:</strong> <?php echo count($backupConfig['tables']); ?>
										<?php endif; ?>
									</div>
									<div class="w-full pl-4">
										<?php
											$backupConfig =  $backup->getConfigFile();

										if(isset($backupConfig['batch']['iterator_position'])):
											$iterator =  $backupConfig['batch']['iterator_position'];
											?>
											<strong>Iterator:</strong> <?php echo $iterator; ?>
											<br />
											<strong>Part:</strong> <?php echo $backupConfig['batch']['part']; ?>
											<br />
											<strong>Total:</strong> <?php echo $backupConfig['batch']['total']; ?>
										<?php endif; ?>
									</div>
								</div>

								<div class="flex justify-between py-4 px-11 bg-white divide-x">
									<details class="w-full pr-4" id="config-database">
										<summary class="rounded border-0 bg-white py-1.5 px-3 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-gray-600 sm:text-sm sm:leading-6 relative">
											Config Database
											<button type="button" class="absolute inset-y-0 right-0 flex items-center rounded-r-md px-2 focus:outline-none">
										</summary>
										<pre class="mt-4">
											<?php print_r($backup->getConfigDatabase()); ?>
										</pre>
									</details>

									<details class="w-full pl-4" id="config-file">
										<summary class="rounded border-0 bg-white py-1.5 px-3 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-gray-600 sm:text-sm sm:leading-6 relative">
											Config File
										</summary>
										<pre class="mt-4">
											<?php print_r($backup->getConfigFile()); ?>
										</pre>
									</details>
								</div>

								<div class="flex justify-between py-4 px-11 bg-white divide-x">
									<div class="flex-1 pr-4">
										<h3 class="font-mono">Tasks</h3>
										<?php if( count($tasks)) : ?>
											<table class="min-w-full divide-y divide-gray-300">
											<thead>
												<tr>
													<th scope="col" class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
													<th scope="col" class="whitespace-nowrap py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">ID</th>
													<th scope="col" class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900">Type</th>
													<th scope="col" class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900">Scheduled At</th>
													<th scope="col" class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900">Start At</th>
													<th scope="col" class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900">End At</th>
													<th scope="col"></th>
												</tr>
											</thead>
											<tbody class="divide-y divide-gray-200">
											<?php foreach($tasks as $task): ?>
												<tr class="middle">
													<td class="whitespace-nowrap inline-flex gap-2 items-center px-2 py-2 text-sm text-italic text-gray-900">
														<?php switch($task->getStatus()) :
															case BackupTaskStatus::ERROR : ?>
																<svg title="<?php echo $task->getStatus(); ?>" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-red-600">
																	<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
																</svg>
																<?php break;
															case BackupTaskStatus::IN_PROGRESS : ?>
																<svg title="<?php echo $task->getStatus(); ?>" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-orange-600">
																	<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
																</svg>
																<?php break;
															case BackupTaskStatus::SUCCESS : ?>
																<svg title="<?php echo $task->getStatus(); ?>" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-green-600">
																	<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
																</svg>
																<?php break;
															default: ?>
																<div class="w-5 h-5 ml-2"></div>
														<?php endswitch; ?>
													</td>
													<td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm text-gray-500 sm:pl-0">#<?php echo $task->getId(); ?></td>
													<td class="whitespace-nowrap px-2 py-2 text-sm font-medium text-gray-900"><?php echo $task->getType(); ?></td>
													<td class="whitespace-nowrap px-2 py-2 text-sm text-gray-900"><?php echo is_null($task->getDateSchedule()) ? 'NULL' : $task->getDateSchedule()->format('Y-m-d H:i:s'); ?></td>
													<td class="whitespace-nowrap px-2 py-2 text-sm text-gray-900"><?php echo is_null($task->getDateStart()) ? 'NULL' : $task->getDateStart()->format('Y-m-d H:i:s'); ?></td>
													<td class="whitespace-nowrap px-2 py-2 text-sm text-gray-900"><?php echo is_null($task->getDateEnd()) ? 'NULL' : $task->getDateEnd()->format('Y-m-d H:i:s'); ?></td>
													<td class="whitespace-nowrap px-2 py-2 text-sm text-gray-900 flex items-center gap-2">
														<?php if($backup->getStatus() === BackupStatus::IN_PROGRESS): ?>
															<?php if(is_null($task->getDateEnd()) && $task->getStatus() !== BackupTaskStatus::IN_PROGRESS): ?>
															<form action="<?php echo admin_url('admin-post.php'); ?>">
																<input type="hidden" name="backup_task_id" value="<?php echo $task->getId(); ?>" />
																<input type="hidden" name="action" value="<?php echo RunManualBackupTask::ACTION; ?>" />
																<?php wp_nonce_field(RunManualBackupTask::ACTION, RunManualBackupTask::NONCE); ?>
																<button class="inline-flex items-center gap-2 rounded border-0 bg-white py-1.5 px-3 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-gray-600 sm:text-sm sm:leading-6" type="submit">
																	<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
																		<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
																	</svg>

																	Run Manual
																</button>
															</form>
															<?php endif; ?>
															<?php if(is_null($task->getDateEnd()) && $task->getStatus() !== BackupTaskStatus::IN_PROGRESS): ?>
															<form action="<?php echo admin_url('admin-post.php'); ?>">
																<input type="hidden" name="backup_task_id" value="<?php echo $task->getId(); ?>" />
																<input type="hidden" name="action" value="<?php echo DeleteBackupTask::ACTION; ?>" />
																<?php wp_nonce_field(DeleteBackupTask::ACTION, DeleteBackupTask::NONCE); ?>
																<button class="inline-flex items-center gap-2 rounded border-0 bg-white py-1.5 px-3 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-gray-600 sm:text-sm sm:leading-6" type="submit">

																	Delete task
																</button>
															</form>
															<?php endif; ?>
														<?php endif; ?>
													</td>
												</tr>
											<?php endforeach; ?>
											</tbody>
										</table>
										<?php else: ?>
											<p class="mt-4"><em>Empty</em></p>
										<?php endif ?>
									</div>
									<div class="flex-1 pl-2">
										<h3 class="px-2 font-mono">Logs</h3>
										<?php if(count($logs) > 0 ) : ?>
											<div class="flex flex-col mt-4">
												<?php foreach($logs as $log):
													$classnames = ['flex', 'gap-2', 'px-2', 'py-1'];
													switch($log->code){
														case LogCode::WARN:
															$classnames[] = 'text-orange-500 font-medium';
															break;
														case LogCode::ERROR:
															$classnames[] = 'bg-red-50 text-red-800 font-medium';
															break;
														case LogCode::SUCCESS:
															$classnames[] = 'bg-green-50 text-green-800 font-medium';
															break;
													}
													?>
													<p class="<?php echo join(' ', $classnames) ?>">
													<?php switch($log->code) :
														case LogCode::INFO : ?>
															<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
																<path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
															</svg>
															<?php break;
														case LogCode::WARN : ?>
															<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
																<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
															</svg>
															<?php break;
														case LogCode::SUCCESS : ?>
															<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
																<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
															</svg>
															<?php break;
														default: ?>
															<span class="w-5 h-5"></span>
														<?php endswitch; ?>
														<span class="font-mono">
															<?php echo $log->created_at; ?>
															<?php echo $log->message; ?>
														</span>
													</p>
												<?php endforeach; ?>
											</div>
										<?php else: ?>
											<p class="mt-4 px-2"><em>Empty</em></p>
										<?php endif ?>
									</div>
								</div>

								<?php endif; ?>
						</div>
					<?php endforeach; ?>

					<!-- More transactions... -->
				</div>
			</div>
		</div>
	</div>
</div>
