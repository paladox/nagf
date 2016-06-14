<?php
class NagfView {
	protected $data;

	public function __construct(stdClass $data) {
		$this->data = $data;
	}

	/**
	 * @return string HTML
	 */
	public function getProjectMenu() {
		$data = $this->data;
		$projects = Graphite::getProjects();
		return '<select name="project" required class="form-control nagf-select-project">'
			. '<option value="" disabled>Select project</option>'
			. implode('', array_map(function ($project) use ($data) {
				return '<option'
					. ($project === $data->project ? ' selected' : '')
					. '>' . htmlspecialchars($project) . '</option>';
			}, $projects))
			. '</select>';
	}

	/**
	 * @return string HTML
	 */
	protected function getHostMenu() {
		$project = $this->data->project;
		$hosts = $this->data->hosts;
		$graphConfigs = $this->data->hostGraphsConfig;

		$html = '<select required class="form-control nagf-select-metric">'
			. '<option value="" disabled>Select metric</option>';

		array_unshift($hosts, 'overview');
		foreach ($hosts as $host) {
			if ($host === 'overview') {
				$title = "$project cluster";
			} else {
				$title = $host;
			}
			$html .= '<optgroup label="' . htmlspecialchars($title) . '">';
			foreach ($graphConfigs as $graphID => &$graph) {
				$html .= '<option value="h_' . htmlspecialchars("{$host}_{$graphID}") . '">'
					. htmlspecialchars($graph['title'])
					. '</option>';
			}
			$html .= '</optgroup>';
		}
		$html .= '</select>';
		return $html;
	}

	/**
	 * @return string HTML
	 */
	public function getHostForm() {
		if (!$this->data->project) {
			return '';
		}
		return '<form class="navbar-form navbar-left only-js" role="form">'
			. '<div class="form-group">'
			. $this->getHostMenu(
				$this->data->project,
				$this->data->hosts,
				$this->data->hostGraphsConfig
			)
			. '</div>'
			. '</form>';
	}

	/**
	 * @return string HTML
	 */
	protected function getRangeMenu() {
		$html = '';
		foreach ($this->data->ranges as $range => $checked) {
			$html .= '<div class="checkbox"><label><input type="checkbox" class="nagf-select-range"'
				. ' value="' . htmlspecialchars($range) . '"'
				. ( $checked ? ' checked' : '' )
				. '> ' . htmlspecialchars(ucfirst($range))
				. '</label></div> ';
		}
		return $html;
	}

	/**
	 * @return string HTML
	 */
	public function getRangeForm() {
		if (!$this->data->project) {
			return '';
		}
		return '<form class="navbar-form navbar-left only-js" role="form">'
			. $this->getRangeMenu()
			. '<button type="submit" class="btn btn-success" id="nagf-select-range-update" hidden>Update</button>'
			. '</form>';
	}

	/**
	 * @return string HTML
	 */
	public function getPage() {
		if ($this->data->status) {
			return $this->getStatusPage();
		}
		if ($this->data->project) {
			return $this->getProjectPage(
				$this->data->project,
				$this->data->hosts,
				$this->data->hostGraphsConfig
			);
		}
		return $this->getHomePage();
	}

	/**
	 * @param string $project
	 * @param Array $hosts
	 * @return string HTML
	 */
	protected function getProjectPage($project, Array $hosts, Array $graphConfigs) {
		$html = '<h1>' . htmlspecialchars($project) . '</h1>';

		array_unshift($hosts, 'overview');

		$sections = array();
		foreach ($hosts as $hostName) {
			if ($hostName === 'overview') {
				$hostTitle = "$project cluster";
				$hostTarget = '*';
			} else {
				$hostTitle = $hostName;
				$hostTarget = $hostName;
			}
			$html .= '<h3 id="h_' . htmlspecialchars($hostName) . '">' . htmlspecialchars($hostTitle) . '</h3>';
			foreach ($graphConfigs as $graphID => &$graph) {
				$html .= '<h4 id="h_' . htmlspecialchars("{$hostName}_{$graphID}") . '">'
					. htmlspecialchars("$hostTitle {$graph['title']}")
					. '</h4>';

				if ($hostName === 'overview') {
					if (is_array($graph['overview'])) {
						$targets = $graph['overview'];
					} else {
						// Default graph for cluster overview: apply sum() to the values
						$targets = array_map(function ($target) use ($graph) {
							return preg_replace('/HOST([^\),]+)/', $graph['overview'] . '(HOST$1)', $target);
						}, $graph['targets']);
					}
				} else {
					$targets = $graph['targets'];
				}

				$targetQuery = '';
				foreach ($targets as $target) {
					$targetQuery .= '&target=' . urlencode(str_replace('HOST', "$project.$hostTarget", $target));
				}

				foreach ($this->data->ranges as $range => $checked) {
					if (!$checked) {
						continue;
					}
					$title = "$hostTitle {$graph['title']} last {$range}";
					$html .= '<img width="800" height="250" src="//graphite.wmflabs.org/render/?'
						. htmlspecialchars(http_build_query(array(
							'title' => $title,
							'width' => 800,
							'height' => 250,
							'from' => '-1' . $range,
							'hideLegend' => 'false',
							'uniqueLegend' => 'true',
						)) . $targetQuery)
						. '" alt="' . htmlspecialchars($title) . '" title="' . htmlspecialchars($title) . '">';
				}
			}
		}

		return $html;
	}

	/**
	 * @return string HTML
	 */
	protected function getHomePage() {
		return '<blockquote>Not another Graphite frontend.</blockquote>'
			. '<p>Select a project from the menu to view the relevant monitoring graphs.</p>';
	}

	/**
	 * @return string HTML
	 */
	protected function getStatusPage() {
		list($code, $msg) = $this->data->status;
		http_response_code($code);
		return '<div class="alert alert-danger" role="alert">'
			. htmlspecialchars($code) . ' Error: <strong>' . htmlspecialchars($msg) . '</strong>'
			. '</div>';
	}

	public function output() {
		$view = $this;
		$pageHtml = $view->getPage();
		$titleHtml = htmlspecialchars($view->data->title);
		$html = <<<HTML
<!DOCTYPE html>
<html dir="ltr" lang="en-US" class="no-js">
<head>
	<meta charset="utf-8">
	<title>{$titleHtml}</title>
	<link rel="stylesheet" href="./lib/bootstrap-3.3.4/css/bootstrap.min.css">
	<link rel="stylesheet" href="./main.css">
<script>document.documentElement.className =
document.documentElement.className.replace( /(^|\s)no-js(\s|$)/, '$1js$2' );
</script>
</head>
<body>
<div class="navbar navbar-default navbar-fixed-top" role="navigation">
	<div class="container">
		<div class="navbar-header">
			<a class="navbar-brand" href="./">Nagf</a>
		</div>
		<div class="navbar-collapse">
			<form class="navbar-form navbar-left" role="form" action="./" method="get">
				<div class="form-group">
					{$view->getProjectMenu()}
				</div>
				<button type="submit" class="btn btn-success only-no-js">Show</button>
			</form>
			{$view->getHostForm()}
			{$view->getRangeForm()}
		</div><!--/.nav-collapse -->
	</div>
</div>
<div class="container">
{$pageHtml}
</div>
<script src="./lib/jquery-1.11.3/jquery.min.js"></script>
<script src="./lib/bootstrap-3.3.4/js/bootstrap.min.js"></script>
<script src="./lib/jquery-cookie/jquery.cookie.js"></script>
<script src="./main.js"></script>
<footer class="nagf-footer" role="contentinfo">
	<div class="container">
		<p>Created by <a href="https://github.com/Krinkle">@Krinkle</a>.</p>
		<p>Code licensed under <a href="http://krinkle.mit-license.org/">MIT</a>.</p>
		<ul class="nagf-footer-links">
			<li><a href="https://github.com/wikimedia/nagf">Source repository</a></li>
			<li>·</li>
			<li><a href="https://github.com/wikimedia/nagf/issues">Issue tracker</a></li>
		</ul>
	</div>
</footer>
</body>
</html>
HTML;
		return $html;
	}

	public static function error(Exception $e, $statusCode = 500) {
		http_response_code($statusCode);
		return '<!DOCTYPE html><title>Error - Nagf</title><pre>'
			. htmlspecialchars(
				get_class($e) . ': ' . $e->getMessage() . "\n"
				. ' in ' . $e->getFile() . ':' . $e->getLine() . "\n\n"
				. $e->getTraceAsString()
			)
			. '</pre>';
	}
}
