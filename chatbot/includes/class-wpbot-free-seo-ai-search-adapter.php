<?php
/**
 * WPBot Free as host of SEO & AI Search: menu below AI Marketing, WPBot's
 * look, labelled inactive previews of the WPBot Pro features, and a short
 * upgrade line in the weekly email that the owner can hide.
 *
 * There is no outreach mailer: sending outreach is a WPBot Pro feature.
 * No Pro code is bundled; the previews are text only.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Host adapter for SEO & AI Search in WPBot Free.
 */
class QCLD_WPBot_Free_Seo_Ai_Search_Adapter extends \QuantumCloud\SeoAiSearch\Host\Abstract_Host_Adapter {

	/**
	 * Host id (the same one WPBot Free uses for WPBot Core).
	 *
	 * @return string
	 */
	public function get_id() {
		return QCLD_WPBot_Free_Core_Adapter::ID;
	}

	/**
	 * Host name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'WPBot';
	}

	/**
	 * Under the WPBot menu.
	 *
	 * @return string
	 */
	public function get_menu_parent() {
		return QCLD_WPBot_Free_Core_Adapter::instance()->get_menu_parent();
	}

	/**
	 * Menu label.
	 *
	 * @return string
	 */
	public function get_menu_title() {
		return __( 'SEO & AI Search', 'chatbot' );
	}

	/**
	 * Directly below AI Marketing, as in WPBot Pro.
	 *
	 * @return string[]
	 */
	public function get_menu_after() {
		return array( 'wpbot-marketing' );
	}

	/**
	 * WPBot brand values.
	 *
	 * @return array
	 */
	public function get_brand() {
		return QCLD_WPBot_Free_Core_Adapter::instance()->get_brand();
	}

	/**
	 * WPBot admin styles, then the page skin.
	 *
	 * @return array
	 */
	public function get_admin_styles() {
		return array_merge(
			QCLD_WPBot_Free_Core_Adapter::instance()->get_admin_styles(),
			array(
				array(
					'handle' => 'qcld-wpbot-free-seo-page',
					'src'    => QCLD_wpCHATBOT_PLUGIN_URL . 'css/seo-ai-search-page.css',
					'ver'    => QCLD_wpCHATBOT_VERSION,
				),
			)
		);
	}

	/**
	 * WPBot's header card.
	 *
	 * @return void
	 */
	public function render_admin_header() {
		QCLD_WPBot_Free_Core_Adapter::instance()->render_admin_header( __( 'SEO & AI Search Growth', 'chatbot' ) );
	}

	/**
	 * Labelled, inactive previews of WPBot Pro features, each with the list
	 * of what WPBot Pro adds. Items with a "tab" show as inactive Pro tabs
	 * inside their section (the same tabs WPBot Pro adds there); the others
	 * are left-menu pages. Text only: nothing runs behind them. Empty once
	 * the owner turns on Settings > General > "Hide Pro previews", so no Pro
	 * card or pill shows anywhere.
	 *
	 * @return array[] Items with id, label, text, features, url and button.
	 */
	public function get_pro_previews() {
		if ( class_exists( '\QuantumCloud\SeoAiSearch\Settings' ) && \QuantumCloud\SeoAiSearch\Settings::get( 'hide_pro_previews' ) ) {
			return array();
		}
		$items = array(
			array(
				'id'       => 'visibility',
				'label'    => __( 'AI Visibility', 'chatbot' ),
				'text'     => __( 'See how AI assistants answer the questions your customers ask, whether they mention or cite your business, and what to change so they do.', 'chatbot' ),
				'features' => array(
					__( 'Track the questions customers ask AI assistants, grouped by topic. Suggestions come from your Search Console searches, chatbot conversations and AI Writer ideas.', 'chatbot' ),
					__( 'Weekly or monthly runs with web search on, using your own AI keys (OpenAI, Claude, Gemini or OpenRouter). A second assistant takes over if the first one fails.', 'chatbot' ),
					__( 'For every answer: whether your business is mentioned, its position, the tone, which competitors are named and which pages are cited.', 'chatbot' ),
					__( 'A visibility score over time for each assistant and your share of voice against competitors.', 'chatbot' ),
					__( 'The questions where you are missing, with the pages assistants cite instead.', 'chatbot' ),
					__( 'Clear actions: pages to create or improve so assistants can cite you, missing facts, FAQ and schema fixes, and AI crawler access fixes.', 'chatbot' ),
					__( 'The estimated monthly cost is shown before you save, and runs stay within your AI budget.', 'chatbot' ),
				),
			),
			array(
				'id'       => 'automation',
				'label'    => __( 'Automation & Alerts', 'chatbot' ),
				'text'     => __( 'Let SEO & AI Search watch your site for you and tell you only when something needs attention.', 'chatbot' ),
				'features' => array(
					__( 'Scheduled technical scans at night in your time zone: daily, weekly or monthly.', 'chatbot' ),
					__( 'Every page re-audited on schedule, with an alert when a page\'s audit score drops.', 'chatbot' ),
					__( 'Alerts for indexing problems, new errors, new 404s, and real traffic or position drops (ordinary weekly swings stay quiet).', 'chatbot' ),
					__( 'Alerts for new opportunities, new competing pages and Keyword Vault changes: a keyword started ranking, dropped out, or a competitor overtook you.', 'chatbot' ),
					__( 'Scheduled link and link exchange checks, with an alert when a partner removes or changes your link.', 'chatbot' ),
					__( 'Autolinking rules (phrase to page) with safeguards, every link logged and removable in one click.', 'chatbot' ),
					__( 'Search history kept beyond Search Console\'s 16 months.', 'chatbot' ),
				),
			),
			array(
				'id'       => 'bulk',
				'label'    => __( 'Bulk Tools', 'chatbot' ),
				'text'     => __( 'Do in one run what Free does one page or one keyword at a time.', 'chatbot' ),
				'features' => array(
					__( 'AI search titles and descriptions for many pages at once. Nothing is saved until you review the proposals.', 'chatbot' ),
					__( 'Fill empty search titles and descriptions on existing posts, without overwriting anything.', 'chatbot' ),
					__( 'Bulk AI fixes for image alt text, FAQs and introductions, and "fix all" for one issue type. Each save can be undone.', 'chatbot' ),
					__( 'Bulk keyword research for many seed words and several countries in one run.', 'chatbot' ),
					__( 'Bulk keyword volume lookups and bulk Keyword Map fixes, with the cost shown first.', 'chatbot' ),
					__( 'Pattern redirects (for example /blog/* to /news/$1) and bulk redirect import.', 'chatbot' ),
					__( 'llms.txt extras: AI-written page descriptions you review, a daily link check, and Markdown copies of your pages.', 'chatbot' ),
				),
			),
			array(
				'id'       => 'competitors',
				'label'    => __( 'Competitor Insights', 'chatbot' ),
				'text'     => __( 'Find out what your competitors rank for and write about that you do not, and keep your competitor list up to date.', 'chatbot' ),
				'features' => array(
					__( 'Competitor keywords: the searches your competitors rank for and you do not, with real volumes from your keyword data provider.', 'chatbot' ),
					__( 'Competitor topic gaps and who ranks for each topic, marked as missing on your Topic Map.', 'chatbot' ),
					__( 'Bulk competitor gap research with real provider data.', 'chatbot' ),
					__( 'Scheduled rank checks through your keyword data provider. Ranks always come from real data, never from AI.', 'chatbot' ),
					__( 'A monthly re-check that suggests new competitors for you to approve. Your list never changes without you.', 'chatbot' ),
					__( 'Competitor text is never stored: only keywords, volumes and positions.', 'chatbot' ),
				),
			),
			array(
				'id'       => 'prospects',
				'label'    => __( 'Competitor link gaps', 'chatbot' ),
				'text'     => __( 'Find the sites that link to your competitors but not to you, and turn them into link prospects.', 'chatbot' ),
				'features' => array(
					__( 'Competitor link gaps from your backlink data provider, listed in Prospects.', 'chatbot' ),
					__( 'A full backlink list in Link Monitor, with new and lost links.', 'chatbot' ),
					__( 'AI-found prospects: mentions of your business without a link, resource pages, broken links, vetted publications and partners. Each is checked before it is saved, and sites that sell links are left out.', 'chatbot' ),
					__( 'Send outreach emails one at a time through AI Marketing\'s email connection, with a daily limit you set and follow-up reminders. Nothing is sent without your click.', 'chatbot' ),
				),
			),
			array(
				'id'       => 'planner',
				'label'    => __( 'Google Keyword Planner', 'chatbot' ),
				'text'     => __( 'Real monthly search numbers and keyword ideas from Google, straight from your Google Ads account.', 'chatbot' ),
				'features' => array(
					__( 'Keyword Planner becomes the source of volumes and ideas everywhere: research, the Keyword Vault, the Topic Map and AI topic gaps.', 'chatbot' ),
					__( 'Keyword Vault numbers filled and refreshed monthly: volume, trend, competition and the bid range.', 'chatbot' ),
					__( 'Supporting searches suggested for each page from the same Keyword Planner group as its main search.', 'chatbot' ),
					__( 'A direct, read-only Google Ads API connection with a daily sync of the last 30 days of search terms and cost per conversion.', 'chatbot' ),
					__( 'An alert when ad costs rise on searches you already rank for. Nothing in Google Ads is ever changed.', 'chatbot' ),
				),
			),
			array(
				'id'       => 'agency',
				'label'    => __( 'Locations & Agency Reports', 'chatbot' ),
				'text'     => __( 'Run local SEO for businesses with several locations, and send clients reports under your own brand.', 'chatbot' ),
				'features' => array(
					__( 'Multiple business locations, each with its own details, opening hours, checks and LocalBusiness schema.', 'chatbot' ),
					__( 'Location pages written with AI Writer from real local details (address, phone, hours and your own notes).', 'chatbot' ),
					__( 'White-label reports with your name, logo and colour and no WPBot branding: a printable report (save it as PDF) and a monthly email to the recipients you choose.', 'chatbot' ),
					__( 'Results by fix type: which kinds of change (AI titles, bulk fixes, internal links, autolinks…) brought the most clicks on this site.', 'chatbot' ),
				),
			),

			// Tabs WPBot Pro adds inside the sections, shown here as inactive tabs.
			array(
				'id'       => 'tab-competitor-keywords',
				'tab'      => 'keywords:competitors',
				'label'    => __( 'Competitor keywords', 'chatbot' ),
				'text'     => __( 'Win the searches your competitors already get traffic from.', 'chatbot' ),
				'features' => array(
					__( 'Find ready-made traffic: see the searches your competitors rank for and you do not, so you know exactly which pages to write next.', 'chatbot' ),
					__( 'Pick the winners first: every keyword comes with its real monthly searches and competitor positions from your keyword data provider, so you spend time only where the demand is.', 'chatbot' ),
					__( 'Watch up to 10 competitors in one place instead of checking their sites by hand.', 'chatbot' ),
					__( 'Size your whole site at once: volumes for your top 1,000 Search Console searches, gathered in the background.', 'chatbot' ),
					__( 'Stay current without effort: a monthly refresh of volumes and backlink data, kept within the budget you set.', 'chatbot' ),
					__( 'Turn gaps into content quickly: missing keywords appear on your Topic Map and as ideas in AI Writer.', 'chatbot' ),
				),
			),
			array(
				'id'       => 'tab-competitor-topics',
				'tab'      => 'keywords:competitor-topics',
				'label'    => __( 'Competitor topics', 'chatbot' ),
				'text'     => __( 'Cover the topics your market expects, so Google and AI assistants see you as the expert.', 'chatbot' ),
				'features' => array(
					__( 'See your coverage at a glance: how many topics your competitors cover and how many of them you cover.', 'chatbot' ),
					__( 'Fill the right gaps: topics competitors write about and you do not are marked as missing on your Topic Map.', 'chatbot' ),
					__( 'Know who you are up against: look up who ranks for any search, with a short AI summary of what those pages do well.', 'chatbot' ),
					__( 'Plan with confidence: gaps flow into AI Writer as new page ideas, ready to brief and write.', 'chatbot' ),
					__( 'Safe by design: no competitor text is ever copied or stored on your site.', 'chatbot' ),
				),
			),
			array(
				'id'       => 'tab-bulk-map',
				'tab'      => 'keywords:bulk-map',
				'label'    => __( 'Bulk map fixes', 'chatbot' ),
				'text'     => __( 'Give every page a clear job in minutes, not days.', 'chatbot' ),
				'features' => array(
					__( 'Save hours of manual work: AI suggests the main and supporting searches for all pages that have no data yet, in one run.', 'chatbot' ),
					__( 'Build topic clusters instantly: pages without a topic are grouped for you, so your Topic Map is complete.', 'chatbot' ),
					__( 'Stay in control: nothing is saved until you review the suggestions, and you can edit any of them first.', 'chatbot' ),
					__( 'Better results everywhere: a complete keyword map makes opportunities, competing-page checks and AI Writer briefs more accurate.', 'chatbot' ),
				),
			),
			array(
				'id'       => 'tab-schedule',
				'tab'      => 'technical:schedule',
				'label'    => __( 'Schedule & alerts', 'chatbot' ),
				'text'     => __( 'Catch problems before they cost you traffic, without remembering to check.', 'chatbot' ),
				'features' => array(
					__( 'Peace of mind: technical scans run on their own every night, week or month, at quiet hours in your time zone.', 'chatbot' ),
					__( 'Find out first: get an email about indexing problems, new 404 errors and real drops in traffic or positions.', 'chatbot' ),
					__( 'No false alarms: normal weekly ups and downs stay quiet, and you choose the minimum traffic worth an alert.', 'chatbot' ),
					__( 'Protect your best pages: every page is re-audited on schedule, with an alert when its score drops.', 'chatbot' ),
					__( 'Never lose a link silently: lost or changed backlinks and broken link exchanges raise an alert too.', 'chatbot' ),
				),
			),
			array(
				'id'       => 'tab-patterns',
				'tab'      => 'technical:patterns',
				'label'    => __( 'Patterns & import', 'chatbot' ),
				'text'     => __( 'Move or restructure your site without losing visitors or rankings.', 'chatbot' ),
				'features' => array(
					__( 'Redirect whole sections in one rule: for example /blog/* to /news/$1 keeps every old link working.', 'chatbot' ),
					__( 'Migrate in minutes: import hundreds of redirects at once from a simple list (old address, new address, code).', 'chatbot' ),
					__( 'Keep the value of old links: visitors and search engines land on the right page instead of a 404.', 'chatbot' ),
					__( 'Clear feedback: see how many redirects were saved and which lines need fixing.', 'chatbot' ),
				),
			),
			array(
				'id'       => 'tab-sending',
				'tab'      => 'links:sending',
				'label'    => __( 'Sending & checks', 'chatbot' ),
				'text'     => __( 'Run link outreach from WordPress and keep every link you earn.', 'chatbot' ),
				'features' => array(
					__( 'Send faster: send outreach emails one by one straight from WordPress through AI Marketing\'s email connection, with a daily limit you set.', 'chatbot' ),
					__( 'Never miss a follow-up: get reminded a week after sending, or on the date you choose.', 'chatbot' ),
					__( 'Protect what you earned: known links and link exchanges are checked on schedule, and you are told when one is lost or changed.', 'chatbot' ),
					__( 'Stay safe: nothing is ever sent without your click, and there is no bulk or automatic emailing.', 'chatbot' ),
				),
			),
			array(
				'id'       => 'tab-backlinks',
				'tab'      => 'links:backlinks',
				'label'    => __( 'Backlink data', 'chatbot' ),
				'text'     => __( 'See every site that links to you, and find the easiest new links to win.', 'chatbot' ),
				'features' => array(
					__( 'A fuller picture: a complete list of links to your site from your keyword data provider, added to Link Monitor.', 'chatbot' ),
					__( 'Spot losses early: links that disappear are marked lost, so you can ask for them back.', 'chatbot' ),
					__( 'Easy wins: competitor link gaps show sites that link to your competitors but not to you, the warmest prospects there are.', 'chatbot' ),
					__( 'Act on them right away: add the good ones as prospects and reach out from the Outreach pipeline.', 'chatbot' ),
				),
			),
		);
		foreach ( $items as $index => $item ) {
			$items[ $index ]['url']    = 'https://www.wpbot.pro/pricing/?utm_source=wpbot-free&utm_medium=seo-preview&utm_campaign=' . $item['id'];
			$items[ $index ]['button'] = __( 'Get WPBot Pro', 'chatbot' );
		}
		return $items;
	}

	/**
	 * WPBot > AI Settings, for the AI readiness notice.
	 *
	 * @return string
	 */
	public function get_ai_settings_url() {
		return QCLD_WPBot_Free_Core_Adapter::instance()->get_ai_settings_url();
	}

	/**
	 * Short upgrade line for the weekly email (Settings > Email & results can hide it).
	 *
	 * @return string
	 */
	public function get_email_footer() {
		return sprintf(
			/* translators: %s: link to WPBot Pro. */
			esc_html__( 'Sent by SEO & AI Search in WPBot. Scheduled scans, alerts and AI visibility tracking come with %s.', 'chatbot' ),
			'<a href="' . esc_url( 'https://www.wpbot.pro/?utm_source=wpbot-free&utm_medium=seo-email&utm_campaign=footer' ) . '">WPBot Pro</a>'
		);
	}
}
