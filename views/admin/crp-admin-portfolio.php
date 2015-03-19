<?php

$crp_portfolioOptionsPage = CRP_ADMIN_VIEWS_DIR_PATH.'/crp-admin-portfolio-options.php';
$crp_pid = 0;

if(isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])){
    $crp_action = 'edit';
    $crp_pid = $_GET['id'];
}else if(isset($_GET['action']) && $_GET['action'] === 'create'){
    $crp_action = 'create';
}

?>

<div class="crp-portfolio-header">

    <input id="crp-portfolio-title" class="crp-portfolio-title" name="portfolio-title" maxlength="250" placeholder="Enter portfolio title" type="text">

    <a id="crp-save-portfolio-button" class='button-secondary portfolio-button' href="#">
        <div class='crp-icon crp-save-icon crp-portfolio-button-icon'> </div>
    </a>
    <a id="crp-portfolio-options-button" class='button-secondary portfolio-button' href="#" onclick="onPortfolioOptions()" style="cursor: not-allowed !important;">
        <div class='crp-icon crp-settings-icon crp-portfolio-button-icon'> </div>
    </a>
</div>

<hr />

<div class="crp-empty-project-list-alert">
    <h3>You don't have projects in this portfolio yet!</h3>
</div>

<div class="crp-project-wrapper">
    <aside class="crp-project-sidebar">
        <div>
            <a id="crp-add-project-button" class='button-secondary crp-add-project-button' href='#' title='Add new'>+ Add new project</a>
        </div>

        <ul id="crp-project-list" class="crp-project-list handles list">
        </ul>
    </aside>
    <section class="crp-project-preview-wrapper">
        <div class="crp-project-details-wrapper">
            <aside class="crp-project-details-sidebar">
                <div id="crp-project-details-content">
                    <input id="crp-project-title" class="crp-project-title" name="project.title" value="" type="text" placeholder="Enter project title">

                    <div id="crp-project-cover-img" class="crp-project-cover-img">
                        <div id="crp-project-cover-img-overlay">
                            <div id="crp-project-cover-img-overlay-content">
                                <div class='crp-icon crp-edit-icon crp-edit-project-cover-icon'> </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="crp-project-cover-src" name="project.cover" value="" />

                    <textarea id="crp-project-description" name="project.description" placeholder="Enter project description..."></textarea>
                    <input id="crp-project-url" name="project.url" value="" type="text" placeholder="Enter project URL">

                    <div id="crp-project-category-wrapper">
                        <textarea id="crp-project-categories" name="project.category" placeholder="Enter project categories"></textarea>
                    </div>
                </div>
            </aside>
            <section class="crp-project-images-wrapper">
                <div class="crp-add-picture-button-wrapper">
                    <a id="crp-add-picture-button" class='button-secondary crp-add-picture-button' href='#' title='Add new'>+ Add new picture</a>
                </div>

                <ul id="crp-project-images-grid" class="crp-project-images-grid sortable grid" style="overflow-y: auto">
                </ul>
            </section>
        </div>
    </section>
</div>

<script>

//Show loading while the page is being complete loaded
crp_showSpinner();

//Configure javascript vars passed PHP
var crp_adminPage = "<?php echo $crp_adminPage ?>";
var crp_portfolioOptionsPage = "<?php echo $crp_portfolioOptionsPage ?>";
var crp_action = "<?php echo $crp_action ?>";
var crp_selectedProjectId = 0;

var crp_categoryAutocompleteDS = [];

//Configure portfolio model
var crp_portfolio = {};
crp_portfolio.id = "<?php echo $crp_pid ?>";
crp_portfolio.projects = {};
crp_portfolio.corder = [];
crp_portfolio.deletions = [];
crp_portfolio.isDraft = true;

jQuery(".crp-project-preview-wrapper").hide();
jQuery(".crp-empty-project-list-alert").show();

//Perform some actions when window is ready
jQuery(window).load(function () {
    //Setup sortable lists and grids
    jQuery('.sortable').sortable();
    jQuery('.handles').sortable({
        handle: 'span'
    });
    jQuery('#crp-project-list').sortable().bind('sortupdate', function(e, ui) {
        //ui.item contains the current dragged element.
        //Triggered when the user stopped sorting and the DOM position has changed.
        crp_updateModel();
    });


    jQuery('#crp-project-categories').tagEditor({
        placeholder: "Enter comma separated categories",
    });

    //In case of edit we sould perform ajax call and retrieve the specified portfolio for editing
    if(crp_action == 'edit'){
        crp_portfolio = crpAjaxGetPortfolioWithId(crp_portfolio.id);

        //NOTE: The validation and moderation is very important thing. Here could be not expected conversion
        //from PHP to Javascript JSON objects. So here we will validate, if needed we will do changes
        //to meet our needs
        crp_portfolio = validatedPortfolio(crp_portfolio);
        //This portfolio is already exists on server, so it's not draft item
        crp_portfolio.isDraft = false;
    }

    //Update UI based on retrieved/(just create) model
    crp_updateUI();

    //When the page is ready, hide loading spinner
    crp_hideSpinner();
});

jQuery( '#crp-project-cover-img' ).on( 'click', function( evt ) {
    // Stop the anchor's default behavior
    evt.preventDefault();

    // Display the media uploader
    crp_openMediaUploader( function callback(picInfo){
         changeProjectCover(picInfo);
    }, false );
});

jQuery("#crp-add-project-button").on( 'click', function( evt ){
    evt.preventDefault();

    //Keep all the changes
    crp_updateModel();

    //Create new draft project
    var crp_project = {};
    crp_project.id = crp_generateId();
    crp_project.isDraft = true;
    crp_project.categories = [];

    crp_portfolio.projects[crp_project.id] = crp_project;
    crp_portfolio.corder.unshift(crp_project.id);

    //Set it as selected
    crp_selectedProjectId = crp_project.id;

    //Update UI
    crp_updateUI();
    jQuery("#crp-project-list").scrollTop(0);
});

jQuery( "#crp-project-list" ).live('click', function(event) {

    var crp_targetElement = null;
    if(jQuery(event.target).hasClass('crp-project-li')){
        crp_targetElement = event.target;
    }else if (jQuery(event.target).hasClass('crp-project-title-label')){
        crp_targetElement = jQuery(event.target).parent();
    }else{
        return;
    }

    var crp_projId = jQuery(crp_targetElement).attr('id');
    if(crp_projId != crp_selectedProjectId){
        crp_updateModel();

        crp_selectedProjectId = crp_projId;
        var _curScrollPos = jQuery("#crp-project-list").scrollTop();

        crp_updateUI();
        jQuery("#crp-project-list").scrollTop(_curScrollPos);
    }
});


jQuery("#crp-save-portfolio-button").on( 'click', function( evt ){
    evt.preventDefault();

    //Apply last changes to the model
    crp_updateModel();

    //Validate saving
    var crp_activeProject = crp_portfolio.projects[crp_selectedProjectId];
    if(!crp_portfolio.title){
        alert("Oops! You're trying to save a project without title.");
        return;
    }

    //Show spinner
    crp_showSpinner();

    //Perform Ajax calls
    crp_result = crpAjaxSavePortfolio(crp_portfolio);

    //Get updated model from the server
    crp_portfolio = crpAjaxGetPortfolioWithId(crp_result['pid']);
    crp_portfolio = validatedPortfolio(crp_portfolio);
    crp_portfolio.isDraft = false;

    crp_selectedProjectId = 0;

    //Update UI
    crp_updateUI();
    jQuery("#crp-project-list").scrollTop(0);

    //Hide spinner
    crp_hideSpinner();
});

jQuery("#crp-add-picture-button").on( 'click', function( evt ){
    evt.preventDefault();

    crp_openMediaUploader( function callback(picInfoArr){
        if(picInfoArr && picInfoArr.length > 0)

        for(var pi = 0; pi < picInfoArr.length; pi++){
            var picInfo = picInfoArr[pi];
            var crp_picId = crp_generateId();

            var innerHTML = "";
            innerHTML +=    "<li id='" + crp_picId + "' class = 'crp-pic-li'>";
            innerHTML +=        "<div id='crp-project-pic-" + crp_picId + "' class='crp-project-pic'>";
            innerHTML +=            "<div class='crp-project-pic-overlay'>";
            innerHTML +=                "<div class='crp-project-pic-overlay-content'>";
            innerHTML +=                    "<div class='crp-icon crp-trash-icon crp-trash-project-pic-icon' onClick='onDeleteProjectPic(\"" + crp_picId + "\")'> </div>";
            innerHTML +=                    "<div class='crp-icon crp-edit-icon crp-edit-project-pic-icon' onClick='onEditProjectPic(\"" + crp_picId + "\")'> </div>";
            innerHTML +=                "</div>";
            innerHTML +=            "</div>"
            innerHTML +=         "</div>"
            innerHTML +=         "<input type='hidden' id='crp-project-pic-src-" + crp_picId + "' value='' />";
            innerHTML +=    "</li>";

            jQuery("#crp-project-images-grid").append( innerHTML );
            changeProjectPic(crp_picId, picInfo);
        }
    }, true );

    jQuery("#crp-project-images-grid").scrollTop(0);
});

jQuery(document).keypress(function(event) {
    //cmd+s or control+s
    if (event.which == 115 && (event.ctrlKey||event.metaKey)|| (event.which == 19)) {
        event.preventDefault();

        jQuery( "#crp-save-portfolio-button" ).trigger( "click" );
        return false;
    }
    return true;
});

function crp_updateUI(){

    if(crp_portfolio.title){
        jQuery("#crp-portfolio-title").attr( "value", crp_portfolio.title );
    }


    jQuery(".crp-project-preview-wrapper").hide();
    jQuery(".crp-empty-project-list-alert").show();

    jQuery("#crp-project-list").empty();
    if(crp_portfolio.projects && crp_portfolio.corder){
        for(var crp_projectIndex = 0; crp_projectIndex < crp_portfolio.corder.length; crp_projectIndex++){

            var crp_projectId = crp_portfolio.corder[crp_projectIndex];
            if(!crp_portfolio.projects[crp_projectId]){
                continue;
            }

            var crp_project = crp_portfolio.projects[crp_projectId];

            var innerHTML = "";
            innerHTML += "<li id='" + crp_project.id +"' class = 'crp-project-li'>";
            innerHTML +=    "<span class = 'draggable'>:: </span>";
            innerHTML +=    "<span class = 'crp-project-title-label'>" + crp_truncateIfNeeded( crp_project.title ? Base64.decode(crp_project.title) : 'Untitled' , 20) + "</span>";
            innerHTML +=    "<div class='crp-icon crp-trash-icon crp-trash-project-icon' onClick='onDeleteProject(\"" + crp_project.id + "\")'> </div>";
            innerHTML += "</li>";
            jQuery("#crp-project-list").append( innerHTML );

            if(!crp_selectedProjectId){
                crp_selectedProjectId = crp_project.id;
            }

            if(crp_project.id == crp_selectedProjectId){
                jQuery("#" + crp_project.id + ".crp-project-li").addClass('active-project-li');

                //Update current project details view
                jQuery("#crp-project-title").attr( "value", (crp_project.title ? Base64.decode(crp_project.title) : '') );
                jQuery("#crp-project-description").attr( "value", Base64.decode(crp_project.description) );
                jQuery("#crp-project-url").attr( "value", (crp_project.url ? crp_project.url : '') );

                //Remove all tags if there are & update it with the new values
                var tags = jQuery('#crp-project-categories').tagEditor('getTags')[0].tags;
                for (var tagI=0; tagI<tags.length; tagI++){ jQuery('#crp-project-categories').tagEditor('removeTag', tags[tagI]); }
                for (var tagI=0; tagI<crp_project.categories.length; tagI++){ jQuery('#crp-project-categories').tagEditor('addTag', crp_project.categories[tagI]); }

                changeProjectCover(crp_project.cover ? JSON.parse(Base64.decode(crp_project.cover)) : null);

                jQuery("#crp-project-images-grid").empty();
                if(crp_project.pics){
                    crp_picInfoList = crp_project.pics.split(",");
                    for(var crp_picIndex=0; crp_picIndex<crp_picInfoList.length; crp_picIndex++){
                        if(!crp_picInfoList[crp_picIndex]) continue;

                        var crp_picId = crp_generateId();

                        var innerHTML = "";
                        innerHTML +=    "<li id='" + crp_picId + "' class = 'crp-pic-li'>";
                        innerHTML +=        "<div id='crp-project-pic-" + crp_picId + "' class='crp-project-pic'>";
                        innerHTML +=            "<div class='crp-project-pic-overlay'>";
                        innerHTML +=                "<div class='crp-project-pic-overlay-content'>";
                        innerHTML +=                    "<div class='crp-icon crp-trash-icon crp-trash-project-pic-icon' onClick='onDeleteProjectPic(\"" + crp_picId + "\")'> </div>";
                        innerHTML +=                    "<div class='crp-icon crp-edit-icon crp-edit-project-pic-icon' onClick='onEditProjectPic(\"" + crp_picId + "\")'> </div>";
                        innerHTML +=                "</div>";
                        innerHTML +=            "</div>"
                        innerHTML +=         "</div>"
                        innerHTML +=         "<input type='hidden' id='crp-project-pic-src-" + crp_picId + "' value='' />";
                        innerHTML +=    "</li>";

                        jQuery("#crp-project-images-grid").append( innerHTML );
                        changeProjectPic(crp_picId, JSON.parse(Base64.decode(crp_picInfoList[crp_picIndex])));
                    }
                }

                jQuery("#crp-project-images-grid").scrollTop(0);

                jQuery(".crp-project-preview-wrapper").show();
                jQuery(".crp-empty-project-list-alert").hide();
            }
        }
    }

    crp_updateCategoryAutocompletePS();
}

function crp_updateModel(){
    crp_portfolio.title = jQuery("#crp-portfolio-title").attr( "value" );
    crp_portfolio.corder = jQuery("#crp-project-list").sortable("toArray");

    if(crp_selectedProjectId){
        var crp_activeProject = crp_portfolio.projects[crp_selectedProjectId];

        crp_activeProject.title = Base64.encode(jQuery("#crp-project-title").attr( "value" ));
        crp_activeProject.cover = Base64.encode(jQuery("#crp-project-cover-src").attr( "value" ));
        crp_activeProject.description = Base64.encode(jQuery("#crp-project-description").attr( "value" ));
        crp_activeProject.url = jQuery("#crp-project-url").attr( "value" );
        crp_activeProject.categories = jQuery("#crp-project-categories").tagEditor('getTags')[0].tags;

        var crp_projectPics = "";
        var crp_picIDsList = jQuery("#crp-project-images-grid").sortable("toArray");
        for(var crp_picIndex = 0; crp_picIndex < crp_picIDsList.length; crp_picIndex++){
            var picInfo = jQuery("#crp-project-pic-src-" + crp_picIDsList[crp_picIndex]).attr( "value" );
            if(picInfo){
                crp_projectPics += Base64.encode(picInfo) + ",";
            }
        }
        if(crp_projectPics.length > 0){
            crp_projectPics = crp_projectPics.substr(0,crp_projectPics.length-1); //Remove last ','
        }
        crp_activeProject.pics = crp_projectPics;

        crp_portfolio.projects[crp_selectedProjectId] = crp_activeProject;
    }
}

function crp_updateCategoryAutocompletePS(){
    crp_categoryAutocompleteDS = [];
    var crp_addedItems = {}; //For tmp usage

    if(crp_portfolio.projects && crp_portfolio.corder){
        for(var crp_projectIndex = 0; crp_projectIndex < crp_portfolio.corder.length; crp_projectIndex++){
            var crp_projectId = crp_portfolio.corder[crp_projectIndex];
            if(!crp_portfolio.projects[crp_projectId]){
                continue;
            }
            var crp_project = crp_portfolio.projects[crp_projectId];
            for(var crp_catIndex = 0; crp_catIndex < crp_project.categories.length; crp_catIndex++ ){
                var category = crp_project.categories[crp_catIndex];
                if(!crp_addedItems[category]){
                    crp_categoryAutocompleteDS.push(category);
                    crp_addedItems[category] = category;
                }
            }
        }
    }

    //TRICK: Destory and reconstruct it again to refresh autocompletion datasouce
    jQuery('#crp-project-categories').tagEditor('destroy');
    jQuery('#crp-project-categories').tagEditor({
        initialTags: [],
        delimiter: ',', /* space and comma */
        placeholder: "Enter comma separated categories",
        forceLowercase: true,
        autocomplete: {
            delay: 0, // show suggestions immediately
            position: { collision: 'flip' }, // automatic menu position up/down
            source: crp_categoryAutocompleteDS
        },
    });
}

function validatedPortfolio(portfolio){
    //NOTE: We use assoc array for projects, so if it's null/undefined or Array,
     //then we should change it as an Object to treat it as an assoc array
    if(!portfolio.projects || (portfolio.projects && crp_isJSArray(portfolio.projects))){
        portfolio.projects = {};
    }

    if(!portfolio.deletions || !(portfolio.deletions && crp_isJSArray(portfolio.deletions))){
        portfolio.deletions = [];
    }

    return portfolio;
}

function onEditProjectPic(crp_picId){
    crp_openMediaUploader( function callback(picInfo){
         changeProjectPic(crp_picId, picInfo);
    }, false );
}

function onDeleteProjectPic(crp_picId){
    jQuery("#"+ crp_picId + ".crp-pic-li").remove();
}

function onDeleteProject(crp_projectId){
    if(!crp_projectId) return;

    //Remove from projects assoc array and add in deletions list
    delete crp_portfolio.projects[crp_projectId];
    crp_portfolio.deletions.push(crp_projectId);

    //Remove from ordered list
    var crp_oi = crp_portfolio.corder.indexOf(crp_projectId);
    if(crp_oi >= 0){
        crp_portfolio.corder.splice(crp_oi,1);
    }

    var _curScrollPos = jQuery("#crp-project-list").scrollTop() - 40;

    //Set it as selected
    if(crp_selectedProjectId == crp_projectId){
        crp_selectedProjectId = 0;
        _curScrollPos = 0;
    }

    crp_updateUI();
    jQuery("#crp-project-list").scrollTop(_curScrollPos);
}

function onPortfolioOptions(){
}

function changeProjectCover(picInfo){
    // After that, set the properties of the image and display it
    jQuery( '#crp-project-cover-img' )
            .css( 'background', 'url(' + (picInfo ? picInfo.medium : CRP_IMAGES_URL + '/general/placeholder-1.png') + ') center no-repeat' )
            .css( 'background-size', 'cover');

    // Store the image's information into the meta data fields
    jQuery( '#crp-project-cover-src' ).val( JSON.stringify(picInfo) );
}

function changeProjectPic(crp_picId, picInfo){
    // After that, set the properties of the image and display it
    jQuery( '#crp-project-pic-' + crp_picId )
            .css( 'background', 'url(' + (picInfo ? picInfo.medium : CRP_IMAGES_URL + '/general/placeholder-1.png') + ') center no-repeat' )
            .css( 'background-size', 'cover');

    // Store the image's information into the meta data fields
    jQuery( '#crp-project-pic-src-' + crp_picId ).val( JSON.stringify(picInfo) );
}

</script>

