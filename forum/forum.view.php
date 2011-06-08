<?php
    /**
     * @class  forumView
     * @author dan (dan.dragan@arnia.ro)
     * @brief  forum module View class
     **/

    class forumView extends forum {

        /**
         * @brief Initialization
         * forum module is divided into general use and administrative purposes
         **/
        function init() {
            /**
             * setting variables for default module configuration
             **/
            if($this->module_info->list_count) $this->list_count = $this->module_info->list_count;
            if($this->module_info->search_list_count) $this->search_list_count = $this->module_info->search_list_count;
            if($this->module_info->page_count) $this->page_count = $this->module_info->page_count;
            $this->except_notice = $this->module_info->except_notice == 'N' ? false : true;

            /**
             * check consultation
             * check to see if current user is an administrator
             **/
            if($this->module_info->consultation == 'Y' && !$this->grant->manager) {
                $this->consultation = true; 
                if(!Context::get('is_logged')) $this->grant->post = false;
            } else {
                $this->consultation = false;
            }

            /**
             * set the template path of the selected skin
             **/
            $template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
            if(!is_dir($template_path)||!$this->module_info->skin) {
                $this->module_info->skin = 'xe_default';
                $template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
            }
            $this->setTemplatePath($template_path);

            /**
             * set the extra keys
             **/
            $oDocumentModel = &getModel('document');
            $extra_keys = $oDocumentModel->getExtraKeys($this->module_info->module_srl);
            Context::set('extra_keys', $extra_keys);

            /** 
             * add the js filters
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'input_password.xml');
            Context::addJsFile($this->module_path.'tpl/js/forum.js');
        }

         /**
         * forum home page - redirects to page defined in config
         */
        function dispForumIndex(){
	        $document_srl = Context::get('document_srl');
	        
        	if(!$document_srl){
	        	$this->dispForumCategoryListIndex(); return;
	            }
	        $this->dispForumContent(); 
	        
        }

         /**
         * Retrieves all forum categories and displays them
         */
        function dispForumCategoryListIndex() {                      
        	if(!$this->grant->access ) return $this->dispForumMessage('msg_not_permitted');

            $this->dispForumCategoryList();

            $this->setTemplateFile('category_index');
        }
        
        /**
         * @brief display forum content
         **/
        function dispForumContent() {
            /**
             * check to see if user has access to view the forum content
             **/
            if(!$this->grant->access ) return $this->dispForumMessage('msg_not_permitted');
			
       		$document_srl = Context::get('document_srl');
       		$category = Context::get('category');
       		$search_keyword=Context::get('search_keyword');
       		
       		
            $logged_info=Context::get('logged_info');
            $oForumModel= &getModel('forum');
            $obj->document_srl=$document_srl;
            $obj->member_srl=$logged_info->member_srl;
            $isNotified= $oForumModel->isNotified($obj);
            
            Context::set('isNotified', $isNotified);
	        
        	if(!$category && !$search_keyword){
	        	$this->dispForumCategoryListIndex();
	            }
            /**
             * displays forum category list
             **/
            $this->dispForumCategoryList();
            /**
             * displays forum category children list only for the categories that have children
             **/
            $this->dispForumCategoryChildren();
            /**
             * displays breadcrumbs on top of each page
             **/
            $this->dispBreadcrumbs();

            // set the search option
            foreach($this->search_option as $opt) $search_option[$opt] = Context::getLang($opt);
            $extra_keys = Context::get('extra_keys');
            if($extra_keys) {
                foreach($extra_keys as $key => $val) {
                    if($val->search == 'Y') $search_option['extra_vars'.$val->idx] = $val->name;
                }
            }
            Context::set('search_option', $search_option);

            // display forum content view
            $this->dispForumContentView();

            // display forum notice list
            $this->dispForumNoticeList();

            // display forum content list
            $this->dispForumContentList();

            /** 
             * add search js filter
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'search.xml');

            // set the template file
            if($category || $search_keyword || $document_srl) {
            	$this->setTemplateFile('list');
            }
        }

        /**
         * @brief display forum category list
         **/
        function dispForumCategoryList(){
                //instancing document model       
                $oDocumentModel = &getModel('document');
                //get all categories list
                $categorylist = $oDocumentModel->getCategoryList($this->module_srl);
                $child_exists=0;
                foreach ($categorylist as $key) {
                	if($key->child_count) $child_exists=1;
                	break;
                }
                //set comment_count for each category
                foreach ($categorylist as $key) {
                	$key->comment_count=0;
                	$args->category_srl=$key->category_srl;
                	$args->module_srl=$this->module_srl;
                	$args->list_count=$oDocumentModel->getCategoryDocumentCount($this->module_srl,$key->category_srl);
                	$output = $oDocumentModel->getDocumentList($args, $this->except_notice);
                	if($output->data) {
                		foreach ($output->data as $document){
                			$comment_count=$document->getCommentCount();
                			$key->comment_count +=$comment_count;
                		}
                	}
                	$key->style_index=1;
                	$key->child_exists=$child_exists;
                	if($child_exists) {
                		if($key->depth) $key->style_index=0;
                	}
                	else {
                	$key->style_index=0;
                	}
                } 
                
	            //set category_list 
                Context::set('category_list', $categorylist);
        }
        
        /**
         * @brief display forum category children
         **/
    	function dispForumCategoryChildren(){
            // instancing document model
            	$oDocumentModel = &getModel('document');
                
            	//get category children
                $categorylist = $oDocumentModel->getCategoryList($this->module_srl);
                $category=Context::get('category');
                $aux[]=$category;
                foreach ($categorylist as $key){
                	if(in_array($key->category_srl, $aux)){
                		foreach($key->childs as $child){
                			$aux[]=$child;
                		}
                		$categorychildren[$key->category_srl]=$key;
                	}
                }
                $child_exists=0;
                if(isset($categorychildren)){
	                foreach ($categorychildren as $key) {
	                	if($key->child_count) $child_exists=1;
	                	break;
	                }
	                // set comment_count for category_children
	                foreach ($categorychildren as $key) {
	                	$key->comment_count=0;
	                	$args->category_srl=$key->category_srl;
	                	$args->module_srl=$this->module_srl;
	                	$args->list_count=$oDocumentModel->getCategoryDocumentCount($this->module_srl,$key->category_srl);
	                	$output = $oDocumentModel->getDocumentList($args, $this->except_notice);
	                	if($output->data) {
	                		foreach ($output->data as $document){
	                			$comment_count=$document->getCommentCount();
	                			$key->comment_count +=$comment_count;
	                		}
	                	}
	                	$key->style_index=1;
	                	$key->child_exists=$child_exists;
	                	if($child_exists) {
	                		if($key->depth) $key->style_index=0;
	                	}
	                	else {
	                	$key->style_index=0;
	                	}
	                } 
                }
                
                Context::set('category_children', $categorychildren);
        }
        
        /**
         * @brief display breadcrumbs
         **/
    	function dispBreadcrumbs(){
            // get current category
            	$category=Context::get('category');
            	$document_srl=Context::get('document_srl');
                $oDocumentModel = &getModel('document');
                //get current document
                $document=$oDocumentModel->getDocument($document_srl);
                if(!$category){
                	$category=$document->variables['category_srl'];
                }
                $categorylist = $oDocumentModel->getCategoryList($this->module_srl);
                $breadcrumbs[$category]=$categorylist[$category];
                $parent_srl=$categorylist[$category]->parent_srl;
                
                while ($parent_srl){
                	foreach ($categorylist as $key){
                		if($key->category_srl  == $parent_srl){
                			$breadcrumbs[$key->category_srl]=$key;
                			$parent_srl=$key->parent_srl;
                			break;
                		}
                	}
                }
                
	            $array_key = array_keys($breadcrumbs);
		        $array_value = array_values($breadcrumbs);
		        
		        $breadcrumbs_return = array();
		        for($i=1, $size_of_array=sizeof($array_key);$i<=$size_of_array;$i++){
		            $breadcrumbs_return[$array_key[$size_of_array-$i]] = $array_value[$size_of_array-$i];
		        }
                //set breadcrumbs
                Context::set('breadcrumbs', $breadcrumbs_return);
        }

        /**
         * @brief display forum content view
         **/
        function dispForumContentView(){
            // get current document_srl
            $document_srl = Context::get('document_srl');
            $page = Context::get('page');

            // instancing document model
            $oDocumentModel = &getModel('document');

            /**
             * get wanted document by document_srl
             **/
            if($document_srl) {
                $oDocument = $oDocumentModel->getDocument($document_srl);

                // if oDocument exists
                if($oDocument->isExists()) {

                    // if the document belongs to another module - inavild request message
                    if($oDocument->get('module_srl')!=$this->module_info->module_srl ) return $this->stop('msg_invalid_request');

                    // check if the current user is manager
                    if($this->grant->manager) $oDocument->setGrant();

                    // check if consultation and not notice
                    if($this->consultation && !$oDocument->isNotice()) {
                        $logged_info = Context::get('logged_info');
                        if($oDocument->get('member_srl')!=$logged_info->member_srl) $oDocument = $oDocumentModel->getDocument(0);
                    }
                    
                // document srl is null and error message
                } else {
                    Context::set('document_srl','',true);
                    $this->alertMessage('msg_not_founded');
                }

            /**
             * create an empty document 
             **/
            } else {
                $oDocument = $oDocumentModel->getDocument(0);
            }

            /**
             * check if the document exists
             **/
            if($oDocument->isExists()) {
                if(!$oDocument->isGranted()) {
                    $oDocument = $oDocumentModel->getDocument(0);
                    Context::set('document_srl','',true);
                    $this->alertMessage('msg_not_permitted');
                } else {
                    // add browser title with the current document title
                    Context::addBrowserTitle($oDocument->getTitleText());

                    // update readed count
                    if( $oDocument->isGranted()) $oDocument->updateReadedCount();

                }
            }

            // set oDocument
            $oDocument->add('module_srl', $this->module_srl);
            Context::set('oDocument', $oDocument);
            
            /** 
             * add insert_comment js filter
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');
        
//            return new Object();
        }

        /**
         * @brief display forum content file list
         **/
        function dispForumContentFileList(){
            $oDocumentModel = &getModel('document');
            $document_srl = Context::get('document_srl');
            $oDocument = $oDocumentModel->getDocument($document_srl);
            Context::set('file_list',$oDocument->getUploadedFiles());
        }

        /**
         * @brief display forum content comment list
         **/
        function dispForumContentCommentList(){
            $oDocumentModel = &getModel('document');
            $document_srl = Context::get('document_srl');
            $oDocument = $oDocumentModel->getDocument($document_srl);
            $comment_list = $oDocument->getComments();
			// set comment_list
            Context::set('comment_list',$comment_list);
        }

        /**
         * @brief display forum notice list
         **/
        function dispForumNoticeList(){
            $oDocumentModel = &getModel('document');
            $args->module_srl = $this->module_srl; 
            $args->category_srl = Context::get('category');
            $notice_output = $oDocumentModel->getNoticeList($args);
            //set notice list
            Context::set('notice_list', $notice_output->data);
        }

        /**
         * @brief display forum content list
         **/
        function dispForumContentList(){
            // instancing document model 
			 $oDocumentModel = &getModel('document');

            // set required arguments
            $args->module_srl = $this->module_srl; 
            $args->page = Context::get('page');
            $args->list_count = $this->list_count; 
            $args->page_count = $this->page_count; 

            // set search arguments
            $args->search_target = Context::get('search_target'); 
            $args->search_keyword = Context::get('search_keyword'); 

            // set category arguments
            $args->category_srl = Context::get('category'); 
			$args->current_category_only=Context::get('current_category_only');
            // ser sort index and oder type
            $args->sort_index = Context::get('sort_index');
            $args->order_type = Context::get('order_type');
            if(!in_array($args->sort_index, $this->order_target)) $args->sort_index = $this->module_info->order_target?$this->module_info->order_target:'list_order';
            if(!in_array($args->order_type, array('asc','desc'))) $args->order_type = $this->module_info->order_type?$this->module_info->order_type:'asc';

            // direct permalink to the specific document
            $_get = $_GET;
            if(!$args->page && ($_GET['document_srl'] || $_GET['entry'])) {
                $oDocument = $oDocumentModel->getDocument(Context::get('document_srl'));
                if($oDocument->isExists() && !$oDocument->isNotice()) {
                    $page = $oDocumentModel->getDocumentPage($oDocument, $args);
                    Context::set('page', $page);
                    $args->page = $page;
                }
            }

            // check consultation
            if($this->consultation) {
                $logged_info = Context::get('logged_info');
                $args->member_srl = $logged_info->member_srl;
            }

            // get notice list
            $notices_output = $oDocumentModel->getNoticeList($args);
            
            $this->except_notice='Y';
            if($args->search_keyword) {
            	if($args->current_category_only != 'Y') $args->category_srl=0;
            }
            $output = $oDocumentModel->getDocumentList($args, $this->except_notice);
        	if($args->search_keyword) {
            	$total_count=count($notices_output->data)+$output->total_count;
            }else {$total_count=$output->total_count;}
            //set the variables
            Context::set('document_list', $output->data);
            Context::set('total_count', $total_count);
            Context::set('total_page', $output->total_page);
            Context::set('page', $output->page);
            Context::set('page_navigation', $output->page_navigation);

            // set list_config
            $oforumModel = &getModel('forum');
            Context::set('list_config', $oforumModel->getListConfig($this->module_info->module_srl));
        }

        /**
         * @brief forum tag list
         **/
        function dispForumTagList() {
            // instancing tag model
            $oTagModel = &getModel('tag');

            $obj->mid = $this->module_info->mid;
            $obj->list_count = 10000;
            $output = $oTagModel->getTagList($obj);

            // if output->data has values
            if(count($output->data)) {
                $numbers = array_keys($output->data);
                shuffle($numbers);

                if(count($output->data)) {
                    foreach($numbers as $k => $v) {
                        $tag_list[] = $output->data[$v];
                    }
                }
            }

            Context::set('tag_list', $tag_list);
			//set template file
            $this->setTemplateFile('tag_list');
        }
        
        /**
         * @brief display forum write
         **/
        function dispForumWrite() {
            // check grant
            if(!$this->grant->post) return $this->dispForumMessage('msg_not_permitted');

            $this->dispBreadcrumbs();
            
            $oDocumentModel = &getModel('document');

            /**
             * Check if user is logged
             **/
            	if(Context::get('is_logged')) {
                    $logged_info = Context::get('logged_info');
                    $group_srls = array_keys($logged_info->group_list);
                } else {
                    $group_srls = array();
                }
                $group_srls_count = count($group_srls);

                // seeking permision to get the list of categories
                $normal_category_list = $oDocumentModel->getCategoryList($this->module_srl);
                if(count($normal_category_list)) {
                    foreach($normal_category_list as $category_srl => $category) {
                        $is_granted = true;
                        if($category->group_srls) {
                            $category_group_srls = explode(',',$category->group_srls);
                            $is_granted = false;
                            if(count(array_intersect($group_srls, $category_group_srls))) $is_granted = true; 

                        }
                        if($is_granted) $category_list[$category_srl] = $category;
                    }
                }
                //set category_list
                Context::set('category_list', $category_list);
           

            // get document_srl
            $document_srl = Context::get('document_srl');
            $oDocument = $oDocumentModel->getDocument(0, $this->grant->manager);
            $oDocument->setDocument($document_srl);
			if($oDocument->get('module_srl') == $oDocument->get('member_srl')) $savedDoc = true;
            $oDocument->add('module_srl', $this->module_srl);

            // check to see if the document exists and is granted
            if($oDocument->isExists()&&!$oDocument->isGranted()) return $this->setTemplateFile('input_password_form');
            if(!$oDocument->isExists()) {
                $oModuleModel = &getModel('module');
                $point_config = $oModuleModel->getModulePartConfig('point',$this->module_srl);
                $logged_info = Context::get('logged_info');
                $oPointModel = &getModel('point');
                $pointForInsert = $point_config["insert_document"];
                if($pointForInsert < 0) {
                    if( !$logged_info ) return $this->dispForumMessage('msg_not_permitted');
                    else if (($oPointModel->getPoint($logged_info->member_srl) + $pointForInsert )< 0 ) return $this->dispForumMessage('msg_not_enough_point');
                }
            }

            Context::set('document_srl',$document_srl);
            Context::set('oDocument', $oDocument);

            //  set the xml js filter
            $oDocumentController = &getController('document');
            $oDocumentController->addXmlJsFilter($this->module_info->module_srl);

            //  set document extra keys
            if($oDocument->isExists() && !$savedDoc) Context::set('extra_keys', $oDocument->getExtraVars());

            /** 
             * add js filter
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'insert.xml');
			//set template file
            $this->setTemplateFile('write_form');
        }

        /**
         * @brief forum delete
         **/
        function dispForumDelete() {
            // check grant
            if(!$this->grant->post) return $this->dispForumMessage('msg_not_permitted');

            // get current document by document_srl
            $document_srl = Context::get('document_srl');

            // if valid document_srl get document
            if($document_srl) {
                $oDocumentModel = &getModel('document');
                $oDocument = $oDocumentModel->getDocument($document_srl);
            }

            // display forum content if document exists
            if(!$oDocument->isExists()) return $this->dispForumContent();

            // if not granted display password form
            if(!$oDocument->isGranted()) return $this->setTemplateFile('input_password_form');

            Context::set('oDocument',$oDocument);

            /** 
             * add js filter
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'delete_document.xml');

            $this->setTemplateFile('delete_form');
        }

        /**
         * @brief forum write comment
         **/
        function dispForumWriteComment() {
        	//get current document_srl from context/link
            $document_srl = Context::get('document_srl');

            // check grants
            if(!$this->grant->post) return $this->dispForumMessage('msg_not_permitted');

            // get all document after instancing document model
            $oDocumentModel = &getModel('document');
            $oDocument = $oDocumentModel->getDocument($document_srl);
            if(!$oDocument->isExists()) return $this->dispForumMessage('msg_invalid_request');

            // instancing comment model
            $oCommentModel = &getModel('comment');
            $oSourceComment = $oComment = $oCommentModel->getComment(0);
            $oComment->add('document_srl', $document_srl);
            $oComment->add('module_srl', $this->module_srl);

            // setting up variables
            Context::set('oDocument',$oDocument);
            Context::set('oSourceComment',$oSourceComment);
            Context::set('oComment',$oComment);

            /** 
             * adding js filter
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');
			//set template file
            $this->setTemplateFile('comment_form');
        }

        /**
         * @brief display forum reply comment
         **/
        function dispForumReplyComment() {
        	//check grants
            if(!$this->grant->post) return $this->dispForumMessage('msg_not_permitted');

            // get parent_srl and document_srl
            $parent_srl = Context::get('comment_srl');
            $document_srl= Context::get('document_srl');
            

            // verify and error message
            if(!$parent_srl && !document_srl) return new Object(-1, 'msg_invalid_request');

            // lookup the comment
            $oCommentModel = &getModel('comment');
            $oSourceComment = $oCommentModel->getComment($parent_srl, $this->grant->manager);
            if(!$oSourceComment->isExists()) {
            	$oDocumentModel=&getModel('document');
            	$oSourceDocument=$oDocumentModel->getDocument($document_srl,$this->grant->manager);
            }

            // If comment doesn't exist then error
            if(!$oSourceComment->isExists() && !$oSourceDocument->isExists()) return $this->dispForumMessage('msg_invalid_request');
            //if(Context::get('document_srl') && $oSourceComment->get('document_srl') != Context::get('document_srl')) return $this->dispForumMessage('msg_invalid_request');

            // set quote styling for comment reply
            $oComment = $oCommentModel->getComment();
            $oComment->add('parent_srl',0);
            if($oSourceComment->isExists()){
            	$oComment->add('document_srl', $oSourceComment->get('document_srl'));
            	$quote = Context::get('quote');
	            $lang->cmd_quote=Context::getLang('cmd_quote');
	            if($quote=='Y')
	            	$content ="<div class=\"quote\"><div class=\"quoteTitle\">".$lang->cmd_quote."</div>".$oSourceComment->get('content')."</div></br>";
            } else if($oSourceDocument->isExists()) {
            			$oComment->add('document_srl', $oSourceDocument->get('document_srl'));
		            	$quote = Context::get('quote');
			            $lang->cmd_quote=Context::getLang('cmd_quote');
			            if($quote=='Y')
			            	$content ="<div class=\"quote\"><div class=\"quoteTitle\">".$lang->cmd_quote."</div>".$oSourceDocument->get('content')."</div></br>";
                     		}
            
            // add content to comment
            $oComment->add('content',$content);

            // set variables
            Context::set('oSourceComment',$oSourceComment);
            Context::set('oComment',$oComment);
            Context::set('module_srl',$this->module_info->module_srl);

            /** 
             * add js filter
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');
			//set template file
            $this->setTemplateFile('comment_form');
        }

        /**
         * @brief display forum modify comment
         **/
        function dispForumModifyComment() {
            // check grants
            if(!$this->grant->post) return $this->dispForumMessage('msg_not_permitted');

            // get document_srl and comment_srl
            $document_srl = Context::get('document_srl');
            $comment_srl = Context::get('comment_srl');
           

            // check comment_srl
            if(!$comment_srl) return new Object(-1, 'msg_invalid_request');

            // get comment
            $oCommentModel = &getModel('comment');
            $oComment = $oCommentModel->getComment($comment_srl, $this->grant->manager);

            // check comment
            if(!$oComment->isExists()) return $this->dispForumMessage('msg_invalid_request');

            // if oComment not granted thet diplay input_password_from template
            if(!$oComment->isGranted()) return $this->setTemplateFile('input_password_form');

            // set variables
            Context::set('oSourceComment', $oCommentModel->getComment());
            Context::set('oComment', $oComment);

            /** 
             * add js filter
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');
			//set template file
            $this->setTemplateFile('comment_form');
        }

        /**
         * @brief display forum delete comment
         **/
        function dispForumDeleteComment() {
            // check grants
            if(!$this->grant->post) return $this->dispForumMessage('msg_not_permitted');

            // get comment_srl
            $comment_srl = Context::get('comment_srl');

            // get comment if comment_srl is not null
            if($comment_srl) {
                $oCommentModel = &getModel('comment');
                $oComment = $oCommentModel->getComment($comment_srl, $this->grant->manager);
            }

            // chech comment
            if(!$oComment->isExists() ) return $this->dispForumContent();

            // if oComment not granted thet diplay input_password_from template
            if(!$oComment->isGranted()) return $this->setTemplateFile('input_password_form');
			// set oComment
            Context::set('oComment',$oComment);

            /** 
             * add js filter
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'delete_comment.xml');
			// set template file
            $this->setTemplateFile('delete_comment_form');
        }

        /**
         * @brief display forum delete trackback
         **/
        function dispForumDeleteTrackback() {
            // get trackback_srl
            $trackback_srl = Context::get('trackback_srl');

            // get trackback
            $oTrackbackModel = &getModel('trackback');
            $output = $oTrackbackModel->getTrackback($trackback_srl);
            $trackback = $output->data;

            // check trackback
            if(!$trackback) return $this->dispForumContent();

            Context::set('trackback',$trackback);

            /** 
             * add js filter
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'delete_trackback.xml');
			// set template file
            $this->setTemplateFile('delete_trackback_form');
        }

        /**
         * @brief forum message
         **/
        function dispForumMessage($msg_code) {
            $msg = Context::getLang($msg_code);
            if(!$msg) $msg = $msg_code;
            Context::set('message', $msg);
            $this->setTemplateFile('message');
        }

        /**
         * @brief alert message
         **/
        function alertMessage($message) {
            $script =  sprintf('<script type="text/javascript"> xAddEventListener(window,"load", function() { alert("%s"); } );</script>', Context::getLang($message));
            Context::addHtmlHeader( $script );
        }
        /**
         * @brief unsubscribe thread used when user clicks on the link in the email notification for unsubscription
         **/
        function unsubscribeThread(){
        	$obj->document_srl=Context::get('document_srl');
            $obj->member_srl=Context::get('member_srl');
            $oDocumenModel=&getModel('document');
            $document=$oDocumenModel->getDocument($obj->document_srl);
            $title=$document->getTitle();
            $output= executeQuery('forum.unsubscribe', $obj);
            
            Context::set('title', $title);
        	$this->setTemplateFile('unsubscribe');
        }

    }
?>