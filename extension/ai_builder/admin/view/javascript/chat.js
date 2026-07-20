/**
 * AI Website Builder — ChatGPT-style interface
 */
(function ($) {
  'use strict';

  function cfg() {
    return window.AI_BUILDER || {};
  }

  var sessionId = 0;
  var isLoading = false;

  var welcomeHtml =
    '<div class="ai-welcome">' +
      '<h1 class="ai-welcome-title">What can I help you build today?</h1>' +
      '<p class="ai-welcome-sub">Manage your OpenCart store with natural language — products, banners, orders, and more.</p>' +
      '<div class="ai-suggestions">' +
        '<button class="ai-suggestion" type="button" data-msg="Change homepage banner">' +
          '<span class="ai-suggestion-title">Change homepage banner</span>' +
          '<span class="ai-suggestion-desc">Replace or update banner slides</span>' +
        '</button>' +
        '<button class="ai-suggestion" type="button" data-msg="Add new product">' +
          '<span class="ai-suggestion-title">Add new product</span>' +
          '<span class="ai-suggestion-desc">Create a product with details &amp; price</span>' +
        '</button>' +
        '<button class="ai-suggestion" type="button" data-msg="Show today\'s orders">' +
          '<span class="ai-suggestion-title">Show today\'s orders</span>' +
          '<span class="ai-suggestion-desc">Get a summary of recent orders</span>' +
        '</button>' +
        '<button class="ai-suggestion" type="button" data-msg="Find customer">' +
          '<span class="ai-suggestion-title">Find customer</span>' +
          '<span class="ai-suggestion-desc">Search customers by name or email</span>' +
        '</button>' +
        '<button class="ai-suggestion" type="button" data-msg="Create coupon">' +
          '<span class="ai-suggestion-title">Create coupon</span>' +
          '<span class="ai-suggestion-desc">Set up a discount code</span>' +
        '</button>' +
        '<button class="ai-suggestion" type="button" data-msg="Increase all prices by 5%">' +
          '<span class="ai-suggestion-title">Bulk price update</span>' +
          '<span class="ai-suggestion-desc">Adjust prices across products</span>' +
        '</button>' +
      '</div>' +
    '</div>';

  $(document).ready(function () {
    initPage();
    initTheme();
    initEvents();
    loadSessions();
    autoResize();
    updateSendButton();
  });

  function initPage() {
    if ($('.ai-api-banner').length) {
      $('#ai-app').addClass('has-api-banner');
    }
    $('body').addClass('ai-chat-page');
  }

  function initTheme() {
    var app = document.getElementById('ai-app');
    if (!app) return;
    var saved = localStorage.getItem('ai_builder_theme') || 'light';
    app.setAttribute('data-theme', saved);
    updateThemeUi(saved);
  }

  function updateThemeUi(theme) {
    var isDark = theme === 'dark';
    $('#btn-theme i').attr('class', isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon');
    $('.ai-theme-label').text(isDark ? 'Light mode' : 'Dark mode');
  }

  function initEvents() {
    $('#btn-send').on('click', sendMessage);
    $('#message-input').on('input', function () {
      autoResizeInput(this);
      updateSendButton();
    });
    $('#message-input').on('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    $('#btn-new-chat').on('click', newSession);
    $('#btn-theme').on('click', toggleTheme);
    $('#btn-toggle-sidebar').on('click', function () {
      closeSidebar();
    });
    $('#btn-open-sidebar').on('click', function () {
      openSidebar();
    });

    $(document).on('click', '.ai-suggestion', function () {
      $('#message-input').val($(this).data('msg'));
      updateSendButton();
      sendMessage();
    });

    $('#btn-attach').on('click', function () { $('#file-input').click(); });
    $('#file-input').on('change', function () {
      if (this.files[0]) uploadFile(this.files[0]);
    });

    $('#search-chats').on('input', function () {
      filterChats($(this).val());
    });

    initDragDrop();

    $('#messages').on('click', '.ai-card', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (isLoading) return;

      var $card = $(this);
      var title = $card.find('.ai-card-title').text().trim();
      var id = $card.attr('data-id');
      var type = $card.attr('data-type') || 'banner';

      if (!id) return;

      $('.ai-card').removeClass('selected');
      $card.addClass('selected');
      sendMessage(title, id, type);
    });

    $('#messages').on('click', '.ai-table-row[data-id]', function (e) {
      e.preventDefault();
      if (isLoading) return;

      var $row = $(this);
      var id = $row.attr('data-id');
      var type = $row.attr('data-type') || 'product';
      var title = $row.find('td').eq(1).text().trim();

      if (!id) return;

      $('.ai-table-row').removeClass('selected');
      $row.addClass('selected');
      sendMessage(title || ('Product #' + id), id, type);
    });

    $('#messages').on('click', '.ai-option', function () {
      sendMessage($(this).text().trim());
    });

    $('#messages').on('click', '.ai-btn-yes', function () {
      var action = $(this).attr('data-action') || '';
      var params = {};

      try {
        params = JSON.parse($(this).attr('data-params') || '{}');
      } catch (e) {
        params = {};
      }

      confirmAction(action, params, true);
    });

    $('#messages').on('click', '.ai-btn-no', function () {
      appendMessage('assistant', 'Action cancelled.');
    });

    $('#messages').on('click', '.ai-upload-zone', function () {
      $('#file-input').click();
    });

    $('#chat-list').on('click', '.ai-chat-item', function () {
      var sid = $(this).data('id');
      loadHistory(sid);
      if (window.innerWidth <= 768) closeSidebar();
    });
  }

  function openSidebar() {
    $('#ai-sidebar').removeClass('collapsed');
    $('#ai-app').removeClass('sidebar-collapsed');
  }

  function closeSidebar() {
    $('#ai-sidebar').addClass('collapsed');
    $('#ai-app').addClass('sidebar-collapsed');
  }

  function filterChats(query) {
    var q = (query || '').toLowerCase();
    $('#chat-list .ai-chat-item').each(function () {
      var text = $(this).text().toLowerCase();
      $(this).toggle(!q || text.indexOf(q) !== -1);
    });
  }

  function updateSendButton() {
    var hasText = !!$('#message-input').val().trim();
    $('#btn-send').prop('disabled', !hasText || isLoading || !cfg().api_configured);
  }

  function initDragDrop() {
    var dropzone = $('#dropzone');
    var composer = $('.ai-composer');

    composer.on('dragover dragenter', function (e) {
      e.preventDefault();
      dropzone.addClass('active');
    });

    composer.on('dragleave drop', function (e) {
      e.preventDefault();
      dropzone.removeClass('active');
    });

    composer.on('drop', function (e) {
      var files = e.originalEvent.dataTransfer.files;
      if (files.length) uploadFile(files[0]);
    });
  }

  function sendMessage(overrideMessage, selectionId, selectionType) {
    if (isLoading || !cfg().api_configured) return;

    var message = (overrideMessage || $('#message-input').val()).trim();
    if (!message) return;

    $('.ai-welcome').remove();
    setChatMode(true);
    appendMessage('user', message);
    $('#message-input').val('').css('height', 'auto');
    updateSendButton();

    isLoading = true;
    showTyping(true);
    updateSendButton();

    $.ajax({
      url: cfg().url_send,
      type: 'POST',
      data: {
        message: message,
        session_id: sessionId,
        selection_id: selectionId || '',
        selection_type: selectionType || ''
      },
      dataType: 'json',
      success: function (json) {
        if (json.session_id) sessionId = json.session_id;
        handleResponse(json);
        loadSessions();
      },
      error: function (xhr) {
        var msg = 'Connection error. Please try again.';
        if (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) {
          msg = xhr.responseJSON.error || xhr.responseJSON.message;
        } else if (xhr.status === 200 && xhr.responseText) {
          msg = 'Invalid server response. Please refresh the page and try again.';
        } else if (xhr.status) {
          msg = 'Request failed (HTTP ' + xhr.status + '). Please try again.';
        }
        appendMessage('assistant', msg);
      },
      complete: function () {
        isLoading = false;
        showTyping(false);
        updateSendButton();
      }
    });
  }

  function handleResponse(json) {
    if (json.error && json.error !== true) {
      appendMessage('assistant', json.message || json.error);
      return;
    }

    if (json.error === true && json.message) {
      appendMessage('assistant', json.message);
      return;
    }

    var $msg;

    if (json.ui && json.ui.type === 'confirm') {
      $msg = appendMessage('assistant', json.ui.message || json.message || 'Please confirm this action.');
      renderUI($msg, json.ui);
    } else {
      $msg = appendMessage('assistant', json.message || '');

      if (json.ui) {
        renderUI($msg, json.ui);
      }

      if (json.needs_confirmation && json.ui && json.ui.type !== 'confirm') {
        renderConfirm($msg, json);
      }
    }

    if (json.preview) {
      $msg.find('.ai-message-content, .ai-message-body').append(
        '<div class="ai-preview"><img src="' + json.preview + '" alt="Preview"/></div>'
      );
    }
  }

  function renderUI($msg, ui) {
    var $body = $msg.find('.ai-message-content, .ai-message-body');
    var html = '';

    switch (ui.type) {
      case 'cards':
        html = '<div class="ai-cards">';
        (ui.items || []).forEach(function (item) {
          var cardType = ui.item_type || item.type || 'banner';
          html += '<div class="ai-card" data-id="' + (item.id || '') + '" data-type="' + esc(cardType) + '">';
          if (item.preview) html += '<img class="ai-card-img" src="' + item.preview + '" alt=""/>';
          html += '<div class="ai-card-body">';
          html += '<div class="ai-card-title">' + esc(item.title || '') + '</div>';
          if (item.meta) html += '<div class="ai-card-meta">' + esc(item.meta) + '</div>';
          html += '</div></div>';
        });
        html += '</div>';
        break;

      case 'table':
        html = '<div class="ai-table-wrap"><table class="ai-table"><thead><tr>';
        (ui.columns || []).forEach(function (col) {
          html += '<th>' + esc(col.label || col.key || '') + '</th>';
        });
        html += '</tr></thead><tbody>';
        (ui.items || []).forEach(function (item) {
          var itemType = ui.item_type || item.type || '';
          html += '<tr class="ai-table-row" data-id="' + esc(String(item.id || '')) + '" data-type="' + esc(itemType) + '">';
          (ui.columns || []).forEach(function (col) {
            html += '<td>' + esc(String(item[col.key] ?? '')) + '</td>';
          });
          html += '</tr>';
        });
        html += '</tbody></table></div>';
        break;

      case 'options':
        html = '<div class="ai-options">';
        (ui.items || []).forEach(function (item) {
          var label = typeof item === 'string' ? item : (item.label || item.title || '');
          html += '<button class="ai-option">' + esc(label) + '</button>';
        });
        html += '</div>';
        break;

      case 'upload':
        html = '<div class="ai-upload-zone"><i class="fa-solid fa-cloud-arrow-up"></i><br>Click or drag to upload</div>';
        break;

      case 'progress':
        var pct = ui.total ? Math.round((ui.valid / ui.total) * 100) : 0;
        html = '<div class="ai-progress">';
        html += '<div class="ai-progress-bar"><div class="ai-progress-fill" style="width:' + pct + '%"></div></div>';
        html += '<div class="ai-progress-stats">';
        html += '<span>Total: ' + (ui.total || 0) + '</span>';
        html += '<span>Valid: ' + (ui.valid || 0) + '</span>';
        html += '<span>Errors: ' + (ui.errors || 0) + '</span>';
        html += '</div></div>';
        break;

      case 'confirm':
        html = '<div class="ai-confirm"><p>' + esc(ui.message || 'Are you sure?') + '</p>';
        html += '<div class="ai-confirm-actions">';
        html += '<button class="ai-btn-yes" data-action="' + esc(ui.action || '') + '" data-params="' + esc(JSON.stringify(ui.params || {})) + '">Yes</button>';
        html += '<button class="ai-btn-no">No</button>';
        html += '</div></div>';
        break;
    }

    if (html) $body.append(html);
  }

  function renderConfirm($msg, json) {
    var ui = json.ui || {};
    renderUI($msg, {
      type: 'confirm',
      message: ui.message || json.message,
      action: json.pending_action || ui.action,
      params: json.pending_params || ui.params || {}
    });
  }

  function confirmAction(action, params, confirmed) {
    isLoading = true;
    showTyping(true);
    updateSendButton();

    $.ajax({
      url: cfg().url_confirm,
      type: 'POST',
      data: {
        action: action,
        params: JSON.stringify(params),
        session_id: sessionId,
        confirmed: confirmed ? 'yes' : 'no'
      },
      dataType: 'json',
      success: function (json) {
        handleResponse(json);
      },
      complete: function () {
        isLoading = false;
        showTyping(false);
        updateSendButton();
      }
    });
  }

  function uploadFile(file) {
    var formData = new FormData();
    formData.append('file', file);
    formData.append('session_id', sessionId);

    isLoading = true;
    showTyping(true);
    updateSendButton();
    setChatMode(true);
    appendMessage('user', '[Uploaded: ' + file.name + ']');

    $.ajax({
      url: cfg().url_upload,
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function (json) {
        if (json.session_id) sessionId = json.session_id;
        handleResponse(json);
      },
      error: function (xhr) {
        var msg = 'Upload failed. Please try again.';
        if (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) {
          msg = xhr.responseJSON.message || xhr.responseJSON.error;
        } else if (xhr.status === 200 && xhr.responseText) {
          msg = 'Invalid server response. Please refresh the page and try again.';
        } else if (xhr.status) {
          msg = 'Upload failed (HTTP ' + xhr.status + '). Please try again.';
        }
        appendMessage('assistant', msg);
      },
      complete: function () {
        isLoading = false;
        showTyping(false);
        updateSendButton();
        $('#file-input').val('');
      }
    });
  }

  function appendMessage(role, content) {
    var icon = role === 'user' ? 'fa-user' : 'fa-wand-magic-sparkles';
    var $msg = $(
      '<div class="ai-message-row ' + role + '">' +
        '<div class="ai-message-inner">' +
          '<div class="ai-message-avatar"><i class="fa-solid ' + icon + '"></i></div>' +
          '<div class="ai-message-content"><pre>' + esc(content) + '</pre></div>' +
        '</div>' +
      '</div>'
    );
    $('#messages').append($msg);
    setChatMode(true);
    scrollToBottom();
    return $msg;
  }

  function showTyping(show) {
    $('#ai-typing-row').remove();
    if (!show) return;

    $('#messages').append(
      '<div class="ai-message-row assistant ai-typing-row" id="ai-typing-row">' +
        '<div class="ai-message-inner">' +
          '<div class="ai-message-avatar"><i class="fa-solid fa-wand-magic-sparkles"></i></div>' +
          '<div class="ai-message-content">' +
            '<div class="ai-typing-dots"><span></span><span></span><span></span></div>' +
            '<span>' + esc(cfg().typing_text || 'Thinking...') + '</span>' +
          '</div>' +
        '</div>' +
      '</div>'
    );
    scrollToBottom();
  }

  function setChatMode(active) {
    $('.ai-main').toggleClass('has-chat', active || $('#messages .ai-message-row').length > 0);
  }

  function newSession() {
    $.post(cfg().url_new_session, function (json) {
      sessionId = json.session_id || 0;
      $('#messages').html(welcomeHtml);
      setChatMode(false);
      loadSessions();
    }, 'json');
  }

  function loadSessions() {
    $.get(cfg().url_sessions, function (json) {
      var html = '';
      (json.sessions || []).forEach(function (s) {
        var active = s.session_id == sessionId ? ' active' : '';
        html += '<div class="ai-chat-item' + active + '" data-id="' + s.session_id + '">' + esc(s.title) + '</div>';
      });
      $('#chat-list').html(html);
      filterChats($('#search-chats').val());
    }, 'json');
  }

  function loadHistory(sid) {
    sessionId = sid;
    $.get(cfg().url_history + '&session_id=' + sid, function (json) {
      $('#messages').empty();
      (json.messages || []).forEach(function (m) {
        if (m.role === 'user' || m.role === 'assistant') {
          var $msg = appendMessage(m.role, m.content);
          if (m.metadata && m.metadata.ui) renderUI($msg, m.metadata.ui);
        }
      });
      if (!$('#messages .ai-message-row').length) {
        $('#messages').html(welcomeHtml);
        setChatMode(false);
      }
      loadSessions();
    }, 'json');
  }

  function toggleTheme() {
    var app = document.getElementById('ai-app');
    if (!app) return;
    var current = app.getAttribute('data-theme') || 'light';
    var next = current === 'dark' ? 'light' : 'dark';
    app.setAttribute('data-theme', next);
    localStorage.setItem('ai_builder_theme', next);
    updateThemeUi(next);
  }

  function scrollToBottom() {
    var el = document.getElementById('messages');
    if (el) el.scrollTop = el.scrollHeight;
  }

  function autoResize() {
    $('#message-input').on('input', function () {
      autoResizeInput(this);
    });
  }

  function autoResizeInput(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 200) + 'px';
  }

  function esc(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }
})(jQuery);
