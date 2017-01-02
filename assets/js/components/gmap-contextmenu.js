/**
 * assets/js/components/gmap-deletemenu.js
 * ----------------------------------------
 * Prototipe menu delete vertex
 */

/**
 * A menu that lets a user delete a selected vertex of a path.
 * @constructor
 */
function VertexContextMenu() {
  this.div_ = document.createElement('div');
  this.div_.className = 'context-menu';
  this.div_.innerHTML = '<div class="arrowborder"></div><div class="arrow"></div>';
  
  this.subMenus = [];
  
  //-- Generate context menus
  this.subMenus.push(document.createElement('a'));
  this.subMenus[0].href = '#';
  this.subMenus[0].className = 'menu-item';
  this.subMenus[0].id = 'ctxmenu-0-delete';
  this.subMenus[0].innerHTML = '&times; Delete';
  this.div_.appendChild(this.subMenus[0]);
  
  this.subMenus.push(document.createElement('a'));
  this.subMenus[1].href = '#';
  this.subMenus[1].className = 'menu-item';
  this.subMenus[1].id = 'ctxmenu-0-setstart';
  this.subMenus[1].innerHTML = 'Set as A';
  this.div_.appendChild(this.subMenus[1]);
  
  this.subMenus.push(document.createElement('a'));
  this.subMenus[2].href = '#';
  this.subMenus[2].className = 'menu-item';
  this.subMenus[2].id = 'ctxmenu-0-setend';
  this.subMenus[2].innerHTML = 'Set as B';
  this.div_.appendChild(this.subMenus[2]);
  
  this.subMenus.push(document.createElement('hr'));
  this.div_.appendChild(this.subMenus[3]);
  
  this.subMenus.push(document.createElement('a'));
  this.subMenus[4].className = 'menu-item';
  this.subMenus[4].id = 'ctxmenu-0-convnode';
  this.subMenus[4].innerHTML = 'Convert to Node';
  this.div_.appendChild(this.subMenus[4]);
  
  var menu = this;
  google.maps.event.addDomListener(this.subMenus[0], 'click', function() {
	  menu.removeVertex();
  });
  google.maps.event.addDomListener(this.subMenus[1], 'click', function() {
	  menu.setAsNodeVertex(1);
  });
  google.maps.event.addDomListener(this.subMenus[2], 'click', function() {
	  menu.setAsNodeVertex(2);
  });
  google.maps.event.addDomListener(this.subMenus[4], 'click', function() {
	  menu.setVertexAsNode();
  });
}
VertexContextMenu.prototype = new google.maps.OverlayView();

VertexContextMenu.prototype.onAdd = function() {
  var ctxMenu = this;
  var map = this.getMap();
  this.getPanes().floatPane.appendChild(this.div_);

  // mousedown anywhere on the map except on the menu div will close the
  // menu.
  this.divListener_ = google.maps.event.addDomListener(map.getDiv(), 'mousedown', function(e) {
    //if (e.target != ctxMenu.div_) {
    //	deleteMenu.close();
    //}
	  if (e.target.className != 'menu-item') {
		  ctxMenu.close();
	  }
  }, true);
};

VertexContextMenu.prototype.onRemove = function() {
  google.maps.event.removeListener(this.divListener_);
  this.div_.parentNode.removeChild(this.div_);

  // clean up
  this.set('position');
  this.set('path');
  this.set('vertex');
};

VertexContextMenu.prototype.close = function() {
	this.setMap(null);
};

VertexContextMenu.prototype.draw = function() {
  var position = this.get('position');
  var projection = this.getProjection();

  if (!position || !projection) {
    return;
  }

  var point = projection.fromLatLngToDivPixel(position);
  this.div_.style.top = point.y + 'px';
  this.div_.style.left = point.x + 'px';
};

/**
 * Opens the menu at a vertex of a given path.
 */
VertexContextMenu.prototype.open = function(map, path, vertex) {
	this.set('position', path.getAt(vertex));
	this.set('path', path);
	this.set('vertex', vertex);
	this.setMap(map);
	this.draw();
};

/**
 * Deletes the vertex from the path.
 */
VertexContextMenu.prototype.removeVertex = function() {
	var path = this.get('path');
	var vertex = this.get('vertex');

	if (!path || vertex == undefined) {
		this.close();
		return;
	}

	this.close();
	path.removeAt(vertex);
};

/**
 * Set vertex as network node
 * @param int vertexFlag Flag. 1 => start node, 2 => end node
 */
VertexContextMenu.prototype.setAsNodeVertex = function(vertexFlag) {
	var path = this.get('path');
	var vertex = this.get('vertex');

	if (!path || vertex == undefined) {
		this.close();
		return;
	}

	this.close();
	
	if (vertexFlag == 1) {
		var uConf = confirm('Set vertex terpilih sebagai node A?');
		if (!uConf) return;
		
		var ctr;
		for (ctr=0; ctr < vertex; ctr++) {
			path.removeAt(0);
		}
		
		edit_edge_setat_(0);
	} else if (vertexFlag == 2) {
		var uConf = confirm('Set vertex terpilih sebagai node B?');
		if (!uConf) return;
		
		var upperBound = path.getLength()-1;
		var ctr;
		for (ctr=upperBound; ctr > vertex; ctr--) {
			path.removeAt(ctr);
		}
		
		edit_edge_setat_(vertex);
	}
	
};

/**
 * Set vertex as node
 */
VertexContextMenu.prototype.setVertexAsNode = function() {
	var path = this.get('path');
	var vertex = this.get('vertex');

	if (!path || vertex == undefined) {
		this.close();
		return;
	}

	this.close();
	
	//-- Panggil!
	edge_break(vertex);
};
