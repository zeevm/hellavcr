posterURL = '';
fx = [];

window.addEvent('domready', function() {
  //add form
  var formSlide = new Fx.Slide('formWrapper').hide();
  $('addButton').addEvent('click', function(e){
    e = new Event(e);
    $('postersLoading').fade('hide');
    formSlide.toggle();
    $('addWrapper').toggleClass('selected');
    if($('addWrapper').hasClass('selected')) $('showName').focus();
    e.stop();
  });
  
  //cancel button
  $('cancelShow').addEvent('click', function(e){
    e = new Event(e);
    $('postersLoading').fade('hide');
    formSlide.toggle();
    $('addWrapper').toggleClass('selected');
    e.stop();
  });
  
  //add button
  $('addShow').addEvent('click', function() {
    new Request({
      method: 'post',
      url: 'index.php',
      onComplete: function(responseText) {
        if(responseText == '1') {
          window.location.href = 'index.php';
        }
        else {
          alert('Error saving the XML, make sure it has global write permissions!');
        }
      }
    }).send('op=add&name=' + escape($('showName').value) + '&dlfull=' + ($('showFullSeries').checked ? '1' : '0') + '&dlnew=' + ($('showNewEpisodes').checked ? '1' : '0') + '&season=' + $('showSeason').value + '&episode=' + $('showEpisode').value + '&format=' + $('showFormat').get('value') + '&language=' + $('showLanguage').get('value') + '&source=' + $('showSource').get('value') + '&poster=' + posterURL);
  });
  
  //delete button
	$$('.delShow').each(function(el) {
		el.addEvent('click', function() {
		  var parts = el.get('id').split('|');
		  
			if(confirm("Delete " + parts[1] + "?")) {
        new Request({
          method: 'post',
          url: 'index.php',
          onComplete: function(responseText) {
            if(responseText == '1') {
              window.location.href = 'index.php';
            }
            else {
              alert('Error saving the XML, make sure it has global write permissions!');
            }
          }
        }).send('op=delete&id=' + parts[0]);
			}
		});
	});
  
  //get posters
  $('getPosters').addEvent('click', function() {
    $('postersLoading').fade('show');
    postersURL = '';
    
    new Request({
      method: 'get',
      url: 'index.php',
      onComplete: function(responseText) {
        var posters_html = '<em>no posters found</em>';
        
        if(responseText.length) {
          //$('addWrapper').tween('height', $('addWrapper').style.height + 150);
          $('addWrapper').setStyle('height', 'auto');
          var posters = responseText.split(',');
          posters_html = '';
          for(var i = 0; i < posters.length; i++) {
            posters_html += '<img alt="" src="' + posters[i] + '" width="76" height="111" onclick="selectPoster(this)" />';
          }
        }
        
        $('selectPosters').set('html', posters_html);
        $('postersLoading').fade('hide');
      }
    }).send('op=get_posters&show_name=' + escape($('showName').value));
  });
  
  //edit name
  $$('.editShow').each(function(el) {
    el.addEvent('click', function() {
      var parts = el.get('id').split('_');
      var infoDiv = $('info_' + parts[1]);
      var editDiv = $('edit_' + parts[1]);
      $('listing_' + parts[1]).morph('.editingListing');
      infoDiv.addClass('editFormWrapper');
      editDiv.removeClass('editFormWrapper');
      editDiv.setStyle('opacity', '0');
      editDiv.fade('in');
      $('postersLoading_' + parts[1]).fade('hide');
      return false;
    });
  });
  
  //cancel edit
  $$('.cancelShow').each(function(el) {
    el.addEvent('click', function() {
      var parts = el.get('id').split('_');
      var infoDiv = $('info_' + parts[1]);
      var editDiv = $('edit_' + parts[1]);
      $('listing_' + parts[1]).morph('.normalListing');
      infoDiv.removeClass('editFormWrapper');
      editDiv.addClass('editFormWrapper');
      infoDiv.setStyle('opacity', '0');
      infoDiv.fade('in');
      return false;
    });
  });
  
  //download history (link)
  $$('.historyLink').each(function(el) {
    //fx[el.rel] = new Fx.Slide(el.rel);
    //fx[el.rel].hide();
    el.addEvent('click', function() {
      $(el.rel).setStyle('display', $(el.rel).getStyle('display') == 'none' ? 'block' : 'none');
      el.toggleClass('historyLinkActive')
      return false;
    });
  });
  
  //download history (div)
  $$('.historySeason').each(function(el) {
    fx[el.id] = new Fx.Slide(el.id);
    fx[el.id].hide();
    $(el.id + '_h2').addEvent('click', function() {
      fx[el.id].toggle();
      this.toggleClass('active');
    });
  });
});

var selectPoster = function(img, id) {
  if(id) {
    $('editform_' + id).poster.value = img.src;
  }
  else {
    posterURL = img.src;
  }
  $$('.selectPosters img').each(function(i) {
    i.removeClass('selected');
    i.addClass('notSelected');
  });
  img.addClass('selected');
};

var getPoster = function(id) {
  $('postersLoading_' + id).fade('show');
  postersURL = '';
  
  new Request({
    method: 'get',
    url: 'index.php',
    onComplete: function(responseText) {
      var posters_html = '<em>no posters found</em>';
      
      if(responseText.length) {
        $('addWrapper').setStyle('height', 'auto');
        var posters = responseText.split(',');
        posters_html = '';
        for(var i = 0; i < posters.length; i++) {
          posters_html += '<img alt="" src="' + posters[i] + '" width="50" onclick="selectPoster(this, \'' + id + '\')" />';
        }
      }
      
      $('selectPosters_' + id).set('html', posters_html);
      $('postersLoading_' + id).fade('hide');
    }
  }).send('op=get_posters&show_name=' + escape($('edit_' + id + '_name').value));
  
  return false;
}