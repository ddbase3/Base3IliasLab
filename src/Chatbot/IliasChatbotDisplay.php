<?php declare(strict_types=1);

namespace Base3IliasLab\Chatbot;

use Chatbot\Content\ChatbotDisplay;

class IliasChatbotDisplay extends ChatbotDisplay {

	public static function getName(): string {
		return 'iliaschatbotdisplay';
	}

	public function getOutput($out = 'html') {
		$html = parent::getOutput($out);

		$html .= '<style>';
		$html .= '.chatbot-main p { margin:8px 0; }';
		$html .= '.chatbot-main table { margin:8px 0; border:1px solid black !important; border-collapse:collapse; }';
		$html .= '.chatbot-main td, .chatbot-main th { padding:3px 5px; border:1px solid black !important; }';
		$html .= '.chatbot-main th { font-weight:bold; }';
		$html .= '.chatbot-main pre { border-left:2px solid #ccc; padding-left:10px; }';
		$html .= '</style>';

		return $html;
	}
} 
