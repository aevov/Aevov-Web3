<?php
/**
 * Data Extractor
 *
 * Extracts structured data from web scrape results using TagFilter.
 *
 * @package AevovVisionDepth\Scraper
 * @since 1.0.0
 */

namespace AevovVisionDepth\Scraper;

class Data_Extractor {

    public function extract($scrape_result) {
        $html = $scrape_result['body'] ?? '';

        return [
            'url' => $scrape_result['url'] ?? null,
            'status_code' => $scrape_result['response']['code'] ?? null,
            'headers' => $scrape_result['response']['headers'] ?? [],
            'body' => $html,
            'title' => $this->extract_title($html),
            'meta_tags' => $this->extract_meta_tags($html),
            'links' => $this->extract_links($html),
            'forms' => $this->extract_forms($html),
            'content' => $this->extract_content($html),
            'timestamp' => time(),
        ];
    }

    private function extract_title($html) {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        }
        return null;
    }

    private function extract_meta_tags($html) {
        $meta_tags = [];
        if (preg_match_all('/<meta\s+([^>]*?)>/is', $html, $matches)) {
            foreach ($matches[1] as $meta) {
                if (preg_match('/name=["\'](.*?)["\']/i', $meta, $name) &&
                    preg_match('/content=["\'](.*?)["\']/i', $meta, $content)) {
                    $meta_tags[$name[1]] = $content[1];
                }
            }
        }
        return $meta_tags;
    }

    private function extract_links($html) {
        $links = [];
        if (preg_match_all('/<a\s+[^>]*href=["\'](.*?)["\']/is', $html, $matches)) {
            $links = array_unique($matches[1]);
        }
        return $links;
    }

    private function extract_forms($html) {
        $forms = [];
        if (preg_match_all('/<form\s+([^>]*?)>(.*?)<\/form>/is', $html, $form_matches, PREG_SET_ORDER)) {
            foreach ($form_matches as $form) {
                $form_data = [
                    'action' => null,
                    'method' => 'GET',
                    'fields' => [],
                ];

                if (preg_match('/action=["\'](.*?)["\']/i', $form[1], $action)) {
                    $form_data['action'] = $action[1];
                }
                if (preg_match('/method=["\'](.*?)["\']/i', $form[1], $method)) {
                    $form_data['method'] = strtoupper($method[1]);
                }

                // Extract input fields
                if (preg_match_all('/<input\s+([^>]*?)>/is', $form[2], $input_matches)) {
                    foreach ($input_matches[1] as $input) {
                        $field = [];
                        if (preg_match('/name=["\'](.*?)["\']/i', $input, $name)) {
                            $field['name'] = $name[1];
                        }
                        if (preg_match('/type=["\'](.*?)["\']/i', $input, $type)) {
                            $field['type'] = $type[1];
                        } else {
                            $field['type'] = 'text';
                        }
                        if (preg_match('/value=["\'](.*?)["\']/i', $input, $value)) {
                            $field['value'] = $value[1];
                        }
                        if (!empty($field)) {
                            $form_data['fields'][] = $field;
                        }
                    }
                }

                $forms[] = $form_data;
            }
        }
        return $forms;
    }

    private function extract_content($html) {
        // Remove script and style tags
        $html = preg_replace('/<script[^>]*?>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*?>.*?<\/style>/is', '', $html);

        // Strip HTML tags
        $text = strip_tags($html);

        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        return substr($text, 0, 5000); // Limit to 5000 characters
    }
}
