<?php

namespace org\desone\wordpress\wpLinkedData;

/**
 * Build an RDF graph from queried wordpress data
 */
class RdfBuilder {

    public function buildGraph ($queriedObject, $wpQuery) {
        $graph = new \EasyRdf_Graph();
        if (!$queriedObject) {
            return $this->buildGraphForBlog ($graph, $wpQuery);
        }
        if ($queriedObject) {
            if ($queriedObject instanceof \WP_User) {
                return $this->buildGraphForUser ($graph, $queriedObject, $wpQuery);
            }
            if ($queriedObject instanceof \WP_Post) {
                return $this->buildGraphForPost ($graph, $queriedObject);
            }
        }
        return $this->buildGraphForAllPostsInQuery ($graph, $wpQuery);
    }

    private function buildGraphForAllPostsInQuery ($graph, $wpQuery) {
        while ($wpQuery->have_posts ()) {
            $wpQuery->next_post ();
            $post = $wpQuery->post;
            $this->buildGraphForPost ($graph, $post);
        }
        return $graph;
    }

    private function buildGraphForPost ($graph, $post) {
        $type = $this->getRdfTypeForPost ($post);
        $post_uri = $this->getPostUri ($post);
        $post_resource = $graph->resource ($post_uri, $type);

        $post_resource->set ('dc:title', $post->post_title);
        $post_resource->set ('sioc:content', strip_tags($post->post_content));
        $post_resource->set ('dc:modified', \EasyRdf_Literal_Date::parse($post->post_modified));
        $post_resource->set ('dc:created', \EasyRdf_Literal_Date::parse($post->post_date));

        $author = get_userdata ($post->post_author);
        $accountUri = $this->getAccountUri ($author);
        $accountResource = $graph->resource ($accountUri, 'sioc:UserAccount');
        $accountResource->set ('sioc:name', $author->display_name);
        $post_resource->set ('sioc:has_creator', $accountResource);

        $blogUri = $this->getBlogUri ();
        $blogResource = $graph->resource ($blogUri, 'sioct:Weblog');
        $post_resource->set ('sioc:has_container', $blogResource);

        return $graph;
    }

    private function getPostUri ($post) {
        return untrailingslashit (get_permalink ($post->ID)) . '#it';
    }

    private function getAuthorUri ($author) {
        return $this->getAuthorDocumentUri ($author) . '#me';
    }

    private function getAccountUri ($author) {
        return $this->getAuthorDocumentUri ($author) . '#account';
    }

    private function getAuthorDocumentUri ($author) {
        return untrailingslashit (get_author_posts_url ($author->ID));
    }

    private function getRdfTypeForPost ($queriedObject) {
        if ($queriedObject->post_type == 'post') {
            return 'sioct:BlogPost';
        }
        return 'sioc:Item';
    }

    private function buildGraphForUser ($graph, $user, $wpQuery) {
        $author_uri = $this->getAuthorUri ($user);
        $account_uri = $this->getAccountUri ($user);
        $author_resource = $graph->resource ($author_uri, 'foaf:Person');
        $account_resource = $graph->resource ($account_uri, 'sioc:UserAccount');

        $author_resource->set ('foaf:name', $user->display_name ?: null);
        $author_resource->set ('foaf:givenName', $user->user_firstname ?: null);
        $author_resource->set ('foaf:familyName', $user->user_lastname ?: null);
        $author_resource->set ('foaf:nick', $user->nickname ?: null);
        $author_resource->set ('bio:olb', $user->user_description ?: null);
        $author_resource->set ('foaf:account', $account_resource);

        $account_resource->set ('sioc:name', $user->display_name ?: null);
        $account_resource->set ('sioc:account_of', $author_resource);

        $this->linkAllPosts ($wpQuery, $graph, $account_resource, 'sioc:creator_of');
        return $graph;
    }

    private function linkAllPosts ($wpQuery, $graph, $resourceToLink, $property) {
        while ($wpQuery->have_posts ()) {
            $wpQuery->next_post ();
            $post = $wpQuery->post;
            $post_uri = $this->getPostUri ($post);
            $post_resource = $graph->resource ($post_uri, 'sioct:BlogPost');
            $post_resource->set ('dc:title', $post->post_title);
            $resourceToLink->add ($property, $post_resource);
        }
    }

    private function buildGraphForBlog ($graph, $wpQuery) {
        $blogUri = $this->getBlogUri ();
        $blogResource = $graph->resource ($blogUri, 'sioct:Weblog');
        $blogResource->set ('rdfs:label', get_bloginfo('name') ?: null);
        $blogResource->set ('rdfs:comment', get_bloginfo('description') ?: null);
        $this->linkAllPosts ($wpQuery, $graph, $blogResource, 'sioc:container_of');
        return $graph;
    }

    private function getBlogUri () {
        return site_url () . '#it';
    }

}