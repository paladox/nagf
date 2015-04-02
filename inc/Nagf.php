<?php
class Nagf {
	/**
	 * @var NagfView
	 */
	private $view;

	public function __construct() {
		$viewData = new stdClass();
		$viewData->title = 'Nagf - wmflabs';
		$viewData->status = null;
		$viewData->project = null;
		$viewData->hosts = null;
		$viewData->hostGraphsConfig = $this->getHostGraphsConfig();

		if (isset($_GET['project'])) {
			$project = $_GET['project'];
			$hosts = Graphite::getHostsForProject($project);
			if ($hosts) {
				$viewData->project = $project;
				$viewData->hosts = $hosts;
			} else {
				$viewData->title = 'Project not found - Nagf';
				$viewData->status = array(404, 'Project not found');
			}
		}

		// NB: Keywords must be compatible with Graphites "from" param (See NagfView::getProjectPage)
		$ranges = array('day', 'week', 'month', 'year');
		// Filter out invalid ranges and ensure we have at least one of them selected
		$cookieRange = isset($_COOKIE['nagf-range']) ? explode('!', $_COOKIE['nagf-range']) : array();
		$checked = array_intersect($ranges, $cookieRange) ?: array( 'day' );

		$viewData->ranges = array();
		foreach ($ranges as $range) {
			$viewData->ranges[$range] = in_array($range, $checked);
		}

		$this->view = new NagfView($viewData);
	}

	public function getView() {
		return $this->view;
	}

	protected function getHostGraphsConfig() {
		return array(
			'cpu' => array(
				'title' => 'CPU',
				'targets' => array(
					'alias(color(stacked(HOST.cpu.total.user.value),"#3333bb"),"User")',
					'alias(color(stacked(HOST.cpu.total.nice.value),"#ffea00"),"Nice")',
					'alias(color(stacked(HOST.cpu.total.system.value),"#dd0000"),"System")',
					'alias(color(stacked(HOST.cpu.total.iowait.value),"#ff8a60"),"Wait I/O")',
					'alias(alpha(color(stacked(HOST.cpu.total.idle.value),"#e2e2f2"),0.4),"Idle")',
				),
				'overview' => 'sum',
			),
			'memory' => array(
				'title' => 'Memory',
				'targets' => array(
					'alias(color(stacked(HOST.memory.Inactive.value),"#5555cc"),"Inactive")',
					'alias(color(stacked(HOST.memory.Cached.value),"#33cc33"),"Cached")',
					'alias(color(stacked(HOST.memory.Buffers.value),"#99ff33"),"Buffers")',
					'alias(alpha(color(stacked(HOST.memory.MemFree.value),"#f0ffc0"),0.4),"Free")',
					'alias(color(stacked(HOST.memory.SwapCached.value),"#9900CC"),"Swap")',
					'alias(color(HOST.memory.MemTotal.value,"red"),"Total")',
				),
				'overview' => array(
					'alias(color(stacked(sum(HOST.memory.Inactive.value)),"#5555cc"),"Inactive")',
					'alias(color(stacked(sum(HOST.memory.Cached.value)),"#33cc33"),"Cached")',
					'alias(color(stacked(sum(HOST.memory.Buffers.value)),"#99ff33"),"Buffers")',
					'alias(alpha(color(stacked(sum(HOST.memory.MemFree.value)),"#f0ffc0"),0.4),"Free")',
					'alias(color(stacked(sum(HOST.memory.SwapCached.value)),"#9900CC"),"Swap")',
					'alias(color(sum(HOST.memory.MemTotal.value),"red"),"Total")',
				),
			),
			'disk' => array(
				'title' => 'Disk space',
				'targets' => array(
					'aliasByNode(HOST.diskspace.*.byte_avail.value,-3,-2)',
				),
				'overview' => array(
					'alias(stacked(sum(HOST.diskspace.*.byte_avail.value)),"byte_avail")',
				),
				'overview' => 'sum',
			),
			'network-bytes' => array(
				'title' => 'Network bytes',
				'targets' => array(
					'alias(HOST.network.eth0.rx_byte.value,"Bytes received")',
					'alias(HOST.network.eth0.tx_byte.value,"Bytes sent")',
				),
				'overview' => 'sum',
			),
			'network-packets' => array(
				'title' => 'Network packets',
				'targets' => array(
					'alias(HOST.network.eth0.rx_packets.value,"Packets received")',
					'alias(HOST.network.eth0.tx_packets.value,"Packets sent")',
				),
				'overview' => 'sum',
			),
			'puppetagent' => array(
				'title' => 'Puppet agent',
				'targets' => array(
					'aliasByNode(HOST.puppetagent.failed_events.value,-2)',
				),
				'overview' => 'stacked',
			),
		);
	}
}
