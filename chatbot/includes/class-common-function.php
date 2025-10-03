<?php
/**
 * Common functions class
 */
class Qcld_WPBot_Common_Functions {

    /**
     * Remove stopwords from search query
     * 
     * @param string $query The search query
     * @param array $stopwords Array of stopwords to remove
     * @return string Query with stopwords removed
     */
    public static function qcpd_remove_wa_stopwords($query, $stopwords) {
        return preg_replace('/\b('.implode('|',$stopwords).')\b/','',$query);
    }

    /**
     * Get relevant page links based on search query
     * 
     * @param string $search_query The search query
     * @return array Array of relevant page links
     */
    public static function qcpd_relevant_pagelink($search_query) {
        $stopwords = explode(',', get_option('qlcd_wp_chatbot_stop_words'));
        
        $finalQueryWordsWithoutStopWords = self::qcpd_remove_wa_stopwords(strtolower($search_query), $stopwords);
        
        $cleanWordsWithoutPunctuationMarks = preg_replace('/[\p{P}]/u', '', $finalQueryWordsWithoutStopWords);
        
        $q = trim($cleanWordsWithoutPunctuationMarks);
        
        $links = [];
        
        $post_type_array = get_option('qcld_openai_relevant_post');
        
        $the_query = new WP_Query(array(
            'post_status' => 'publish',
            'posts_per_page' => 5,
            's' => esc_attr($q),
            'post_type' => $post_type_array
        ));
        
        if($the_query->have_posts()) {
            while($the_query->have_posts()) {
                $the_query->the_post();
                
                $url = esc_url(get_permalink());
                $link = '<a href=' . $url . ' target="_blank">' . get_the_title() . '</a>';
                array_push($links, $link);
            }
            wp_reset_postdata();
        }
        
        $links = array_unique($links);
        return $links;
    }

}
