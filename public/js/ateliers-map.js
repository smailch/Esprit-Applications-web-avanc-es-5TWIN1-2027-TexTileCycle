/* global L */
(function () {
  'use strict';

  var TUNIS = [36.8065, 10.1815];
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function refreshIcons() {
    if (window.lucide) window.lucide.createIcons();
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = String(text);
    return node;
  }

  function sameOriginUrl(url) {
    try {
      var parsed = new URL(url, window.location.href);
      return parsed.origin === window.location.origin ? parsed.href : null;
    } catch (e) {
      return null;
    }
  }

  function readMarkers(wrapper) {
    var script = wrapper && wrapper.querySelector('script[data-map-markers]');
    if (!script) return [];
    try {
      var data = JSON.parse(script.textContent || '[]');
      return Array.isArray(data) ? data : [];
    } catch (e) {
      return [];
    }
  }

  function pinIcon() {
    return L.divIcon({
      className: 'atelier-pin',
      html: '<span class="atelier-pin__shape"></span>',
      iconSize: [28, 34],
      iconAnchor: [14, 32],
      popupAnchor: [0, -30]
    });
  }

  /* Popup construite nœud par nœud : aucune donnée d'atelier ne passe par innerHTML. */
  function buildPopup(m) {
    var root = el('div', 'atelier-popup');
    root.appendChild(el('p', 'atelier-popup__title', m.nom));

    var meta = [m.ville, m.distance ? 'à ' + m.distance : null].filter(Boolean).join(' · ');
    if (meta) root.appendChild(el('p', 'atelier-popup__meta', meta));
    if (m.specialite) root.appendChild(el('p', 'atelier-popup__spec', m.specialite));

    var rating = el('p', 'atelier-popup__rating');
    var star = rating.appendChild(el('span', 'atelier-popup__star', '★'));
    star.setAttribute('aria-hidden', 'true');
    rating.appendChild(document.createTextNode(' ' + m.note + ' sur 5'));
    root.appendChild(rating);

    var url = sameOriginUrl(m.url);
    if (url) {
      var link = el('a', 'atelier-popup__link', 'Voir la fiche');
      link.href = url;
      link.setAttribute('aria-label', 'Voir la fiche de ' + m.nom);
      root.appendChild(link);
    }

    return root;
  }

  function AtelierMap(canvas) {
    this.canvas = canvas;
    this.wrapper = canvas.closest('.atelier-map');
    this.mode = canvas.getAttribute('data-mode') || 'list';
    this.status = this.wrapper ? this.wrapper.querySelector('[data-map-status]') : null;
    this.markers = new Map();
    this.handlers = {};
    this.userMarker = null;
    this.needsFit = false;

    canvas.textContent = '';

    this.map = L.map(canvas, { scrollWheelZoom: false, zoomControl: true });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(this.map);
    this.layer = L.featureGroup().addTo(this.map);

    /* Molette active seulement après une interaction volontaire, pour ne pas piéger le défilement. */
    var map = this.map;
    map.on('click focus', function () { map.scrollWheelZoom.enable(); });
    map.on('mouseout blur', function () { map.scrollWheelZoom.disable(); });

    var lat = parseFloat(canvas.getAttribute('data-user-lat'));
    var lng = parseFloat(canvas.getAttribute('data-user-lng'));
    if (isFinite(lat) && isFinite(lng)) this.setUser(lat, lng);

    this.render(readMarkers(this.wrapper));
  }

  AtelierMap.prototype.on = function (name, fn) {
    (this.handlers[name] = this.handlers[name] || []).push(fn);
  };

  AtelierMap.prototype.emit = function (name, payload) {
    (this.handlers[name] || []).forEach(function (fn) { fn(payload); });
  };

  AtelierMap.prototype.render = function (markers) {
    var self = this;
    this.layer.clearLayers();
    this.markers.clear();

    markers.forEach(function (m) {
      var lat = Number(m.latitude);
      var lng = Number(m.longitude);
      if (!isFinite(lat) || !isFinite(lng)) return;

      var id = String(m.id);
      var marker = L.marker([lat, lng], {
        icon: pinIcon(),
        title: String(m.nom || ''),
        keyboard: true,
        riseOnHover: true
      });
      marker.bindPopup(buildPopup(m), { autoPanPadding: [24, 24], maxWidth: 260 });
      marker.on('click', function () { self.emit('select', id); });
      marker.addTo(self.layer);
      self.markers.set(id, marker);
    });

    if (this.status) {
      this.status.textContent = this.markers.size === 0 && this.mode === 'list'
        ? 'Aucun atelier géolocalisé à afficher pour ces critères.'
        : '';
    }

    this.fit();
  };

  AtelierMap.prototype.fit = function () {
    if (this.canvas.clientWidth === 0) {
      this.needsFit = true;
      return;
    }
    this.needsFit = false;

    var points = [];
    this.markers.forEach(function (marker) { points.push(marker.getLatLng()); });
    if (this.userMarker) points.push(this.userMarker.getLatLng());

    if (points.length === 0) {
      this.map.setView(TUNIS, 11);
    } else if (points.length === 1) {
      this.map.setView(points[0], this.mode === 'single' ? 15 : 14);
    } else {
      this.map.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 15 });
    }
  };

  AtelierMap.prototype.refreshSize = function () {
    this.map.invalidateSize();
    if (this.needsFit) this.fit();
  };

  AtelierMap.prototype.setUser = function (lat, lng) {
    if (this.userMarker) {
      this.userMarker.setLatLng([lat, lng]);
      return;
    }
    this.userMarker = L.circleMarker([lat, lng], {
      radius: 8,
      color: '#ffffff',
      weight: 3,
      fillColor: '#3f83c9',
      fillOpacity: 1
    }).bindTooltip('Votre position').addTo(this.map);
  };

  AtelierMap.prototype.clearUser = function () {
    if (this.userMarker) {
      this.map.removeLayer(this.userMarker);
      this.userMarker = null;
    }
  };

  AtelierMap.prototype.highlight = function (id, options) {
    options = options || {};
    this.markers.forEach(function (marker, key) {
      var active = key === id;
      var node = marker.getElement();
      if (node) node.classList.toggle('is-active', active);
      marker.setZIndexOffset(active ? 1000 : 0);
    });

    var marker = id ? this.markers.get(id) : null;
    if (marker && options.open) {
      if (options.pan) {
        this.map.setView(marker.getLatLng(), Math.max(this.map.getZoom(), 14), { animate: !reduceMotion });
      }
      marker.openPopup();
    }
  };

  /* ------------------------------------------------------------------ */
  /* Page liste : filtres, géolocalisation, synchronisation liste/carte  */
  /* ------------------------------------------------------------------ */

  function initListPage(page) {
    var canvas = page.querySelector('[data-atelier-map]');
    var amap = canvas ? canvas._atelierMap : null;
    var form = page.querySelector('[data-filters-form]');
    var layout = page.querySelector('[data-layout]');
    var switcher = page.querySelector('[data-view-switch]');
    var results = page.querySelector('[data-swap="results"]');
    var locateBtn = page.querySelector('[data-locate]');
    var locateStatus = page.querySelector('[data-locate-status]');
    var endpoint = canvas ? canvas.getAttribute('data-endpoint') : null;
    var mobile = window.matchMedia('(max-width: 850px)');
    var activeId = null;
    var controller = null;

    if (!form || !results || !layout) return;

    function cardOf(id) {
      var selector = window.CSS && CSS.escape ? CSS.escape(id) : id;
      return results.querySelector('[data-atelier-id="' + selector + '"]');
    }

    function setActiveCard(id) {
      results.querySelectorAll('.atelier-card.is-active').forEach(function (card) { card.classList.remove('is-active'); });
      var card = id ? cardOf(id) : null;
      if (card) card.classList.add('is-active');
      activeId = id;
      return card;
    }

    /* Bascule Liste / Carte (mobile) */
    function setView(view) {
      layout.setAttribute('data-view', view);
      if (switcher) {
        switcher.querySelectorAll('[data-view-btn]').forEach(function (btn) {
          btn.setAttribute('aria-pressed', String(btn.getAttribute('data-view-btn') === view));
        });
      }
      if (view === 'map' && amap) window.setTimeout(function () { amap.refreshSize(); }, 0);
    }

    if (switcher) {
      switcher.hidden = false;
      switcher.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-view-btn]');
        if (btn) setView(btn.getAttribute('data-view-btn'));
      });
    }

    var onBreakpoint = function () { if (amap) amap.refreshSize(); };
    if (mobile.addEventListener) mobile.addEventListener('change', onBreakpoint);
    else if (mobile.addListener) mobile.addListener(onBreakpoint);

    /* Liste -> carte (délégation : la liste est remplacée à chaque recherche) */
    function previewCard(e) {
      var card = e.target.closest('.atelier-card');
      if (card && amap) amap.highlight(card.getAttribute('data-atelier-id'));
    }
    results.addEventListener('mouseover', previewCard);
    results.addEventListener('focusin', previewCard);
    results.addEventListener('mouseleave', function () { if (amap) amap.highlight(activeId); });

    results.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-show-on-map]');
      if (!btn || !amap) return;
      var id = btn.getAttribute('data-show-on-map');
      setActiveCard(id);
      if (mobile.matches) setView('map');
      window.setTimeout(function () { amap.highlight(id, { open: true, pan: true }); }, mobile.matches ? 60 : 0);
    });

    /* Carte -> liste */
    if (amap) {
      amap.on('select', function (id) {
        var card = setActiveCard(id);
        amap.highlight(id);
        if (card && !mobile.matches) {
          card.scrollIntoView({ block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' });
        }
      });
    }

    /* Rechargement des résultats sans recharger la page */
    function urlFromForm() {
      var params = new URLSearchParams();
      new FormData(form).forEach(function (value, key) {
        if (typeof value === 'string' && value.trim() !== '') params.append(key, value.trim());
      });
      var qs = params.toString();
      return form.action + (qs ? '?' + qs : '');
    }

    function swapRegions(doc) {
      page.querySelectorAll('[data-swap]').forEach(function (region) {
        var fresh = doc.querySelector('[data-swap="' + region.getAttribute('data-swap') + '"]');
        if (!fresh) return;
        var nodes = Array.prototype.map.call(fresh.childNodes, function (n) { return document.importNode(n, true); });
        region.replaceChildren.apply(region, nodes);
      });
    }

    function syncForm(doc) {
      var freshForm = doc.querySelector('[data-filters-form]');
      if (!freshForm) return;

      Array.prototype.forEach.call(form.elements, function (field) {
        if (!field.name) return;
        var fresh = freshForm.querySelector('[name="' + field.name + '"]');
        if (!fresh) return;
        if (field.tagName === 'SELECT') {
          Array.prototype.forEach.call(field.options, function (option, i) {
            if (fresh.options[i]) option.disabled = fresh.options[i].disabled;
          });
        }
        if (document.activeElement !== field || field.tagName !== 'INPUT') field.value = fresh.value;
        field.disabled = fresh.disabled;
      });

      var rayonField = form.querySelector('[data-rayon-field]');
      var freshRayon = freshForm.querySelector('[data-rayon-field]');
      if (rayonField && freshRayon) rayonField.className = freshRayon.className;

      var freshLocate = freshForm.querySelector('[data-locate]');
      if (locateBtn && freshLocate) {
        locateBtn.className = freshLocate.className;
        locateBtn.replaceChildren.apply(locateBtn, Array.prototype.map.call(freshLocate.childNodes, function (n) { return document.importNode(n, true); }));
      }

      if (amap) {
        var lat = parseFloat(freshForm.querySelector('[data-position-lat]').value);
        var lng = parseFloat(freshForm.querySelector('[data-position-lng]').value);
        var hasPosition = !freshForm.querySelector('[data-position-lat]').disabled && isFinite(lat) && isFinite(lng);
        if (hasPosition) amap.setUser(lat, lng);
        else amap.clearUser();
      }
    }

    function navigate(url, push, options) {
      options = options || {};
      if (!window.fetch || !window.DOMParser || !window.AbortController) {
        window.location.href = url;
        return;
      }

      if (controller) controller.abort();
      var current = controller = new AbortController();
      var target = new URL(url, window.location.href);
      var mapUrl = endpoint ? new URL(endpoint, window.location.href) : null;
      if (mapUrl) {
        target.searchParams.forEach(function (value, key) {
          if (key !== 'page') mapUrl.searchParams.append(key, value);
        });
      }
      var focused = document.activeElement;

      results.setAttribute('aria-busy', 'true');
      page.classList.add('is-loading');

      Promise.all([
        fetch(target.href, { headers: { Accept: 'text/html' }, credentials: 'same-origin', signal: current.signal }),
        mapUrl
          ? fetch(mapUrl.href, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: current.signal })
          : Promise.resolve(null)
      ]).then(function (responses) {
        var html = responses[0];
        var json = responses[1];
        if (!html.ok || html.redirected || (json && !json.ok)) throw new Error('fallback');
        return Promise.all([html.text(), json ? json.json() : null]);
      }).then(function (data) {
        var doc = new DOMParser().parseFromString(data[0], 'text/html');
        swapRegions(doc);
        syncForm(doc);
        activeId = null;
        if (amap && data[1]) amap.render(Array.isArray(data[1].data) ? data[1].data : []);
        if (push) window.history.pushState({ ateliers: true }, '', target.pathname + target.search);
        refreshIcons();

        if (!document.body.contains(focused) || options.scrollToResults) {
          results.setAttribute('tabindex', '-1');
          results.focus({ preventScroll: true });
        }
        if (options.scrollToResults) {
          layout.scrollIntoView({ block: 'start', behavior: reduceMotion ? 'auto' : 'smooth' });
        }
      }).catch(function (err) {
        if (err && err.name === 'AbortError') return;
        window.location.href = target.href;
      }).finally(function () {
        if (controller !== current) return;
        results.removeAttribute('aria-busy');
        page.classList.remove('is-loading');
      });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      navigate(urlFromForm(), true);
    });

    form.addEventListener('change', function (e) {
      if (e.target.matches('[data-auto-submit]')) navigate(urlFromForm(), true);
    });

    page.addEventListener('click', function (e) {
      var link = e.target.closest('a[data-ajax-link]');
      if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      e.preventDefault();
      navigate(link.href, true, { scrollToResults: !!link.closest('.tc-pagination') });
    });

    window.addEventListener('popstate', function () {
      navigate(window.location.href, false);
    });

    /* Géolocalisation */
    function say(message, isError) {
      if (!locateStatus) return;
      locateStatus.textContent = message;
      locateStatus.classList.toggle('is-error', !!isError);
    }

    if (locateBtn) {
      locateBtn.addEventListener('click', function () {
        if (!navigator.geolocation) {
          say('La géolocalisation n’est pas disponible dans ce navigateur. Vous pouvez filtrer par ville.', true);
          return;
        }

        var label = locateBtn.querySelector('[data-locate-label]');
        var restore = function () {
          locateBtn.disabled = false;
          locateBtn.removeAttribute('aria-busy');
        };

        locateBtn.disabled = true;
        locateBtn.setAttribute('aria-busy', 'true');
        if (label) label.textContent = 'Localisation…';
        say('Recherche de votre position…');

        navigator.geolocation.getCurrentPosition(function (position) {
          var lat = position.coords.latitude.toFixed(5);
          var lng = position.coords.longitude.toFixed(5);
          var latInput = form.querySelector('[data-position-lat]');
          var lngInput = form.querySelector('[data-position-lng]');
          var rayon = form.querySelector('[name="rayon_km"]');
          var tri = form.querySelector('[name="tri"]');
          var triDistance = form.querySelector('[data-tri-distance]');

          latInput.value = lat;
          lngInput.value = lng;
          latInput.disabled = false;
          lngInput.disabled = false;
          if (rayon) rayon.disabled = false;
          if (triDistance) triDistance.disabled = false;
          if (tri) tri.value = 'distance';

          restore();
          say('Position trouvée : les ateliers sont triés du plus proche au plus éloigné.');
          if (amap) amap.setUser(Number(lat), Number(lng));
          navigate(urlFromForm(), true);
        }, function (error) {
          restore();
          if (label) label.textContent = 'Me localiser';
          var messages = {
            1: 'Vous avez refusé l’accès à votre position. Pour trier par distance, autorisez la localisation pour ce site dans les réglages du navigateur, ou filtrez par ville.',
            2: 'Votre position est introuvable pour le moment. Vérifiez que la localisation de l’appareil est activée.',
            3: 'La localisation a pris trop de temps. Réessayez dans un instant.'
          };
          say(messages[error.code] || messages[2], true);
        }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var canvases = document.querySelectorAll('[data-atelier-map]');

    if (!window.L) {
      canvases.forEach(function (canvas) {
        var text = canvas.querySelector('.atelier-map__fallback span');
        if (text) text.textContent = 'La carte n’a pas pu être chargée. La liste reste disponible.';
      });
    } else {
      canvases.forEach(function (canvas) {
        canvas._atelierMap = new AtelierMap(canvas);
      });
    }

    var page = document.querySelector('[data-ateliers-page]');
    if (page) initListPage(page);

    refreshIcons();
  });
})();
