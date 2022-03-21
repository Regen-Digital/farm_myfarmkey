(function () {
  farmOS.map.behaviors.myfarmkey_import = {
    attach: function (instance) {

      // Check if there are asset type layers to add.
      if (instance.farmMapSettings.myfarmkey_import.geojson_file !== undefined) {

        // Add layers for each area type.
        const filePath = instance.farmMapSettings.myfarmkey_import.geojson_file;

        // Build a url to the asset type geojson, default to all.
        const url = new URL(filePath, window.location.origin + drupalSettings.path.baseUrl);

        // Build the layer.
        var opts = {
          title: 'MyFarmKey Cadastral',
          url,
          color: 'red',
        };

        var newLayer = instance.addLayer('geojson', opts);

        // If zoom is true, zoom to the layer vectors.
        // Do not zoom to cluster layers.
        var source = newLayer.getSource();
        source.on('change', function () {
          instance.zoomToVectors();
        });
      }

      // Load area details via AJAX when an area popup is displayed.
      instance.popup.on('farmOS-map.popup', function (event) {
        var link = event.target.element.querySelector('.ol-popup-name a');
        if (link) {
          var assetLink = link.getAttribute('href')
          var description = event.target.element.querySelector('.ol-popup-description');

          // Add loading text.
          var loading = document.createTextNode('Loading asset details...');
          description.appendChild(loading);

          // Create an iframe linking to the map_popup view mode.
          var frame = document.createElement('iframe');
          frame.setAttribute('src', assetLink + '/map-popup');
          frame.onload = function () {
            description.removeChild(loading);
            instance.popup.panIntoView();
          }
          description.appendChild(frame);
        }
      });
    }
  };
}());
