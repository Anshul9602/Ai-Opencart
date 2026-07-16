/**
 * AI Website Builder — Chat Interface JavaScript
 */
(function ($) {
  'use strict';

  function cfg() {
    return window.AI_BUILDER || {};
  }

  var sessionId = 0;
  var isLoading = false;

  $(document).ready(function () {
    initTheme();
    initEvents();
    loadSessions();
    autoResize();
  });

  function initTheme() {
    var app = document.getElementById('ai-app');
    if (!app) return;
    var saved = localStorage.getItem('ai_builder_theme') || 'light';
    app.setAttribute('data-theme', saved);
    updateThemeIcon(saved);
  }

  function updateThemeIcon(theme) {
    $('#btn-theme i').attr('class', theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon');
  }

  function initEvents() {
    $('#btn-send').on('click', sendMessage);
    $('#message-input').on('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    $('#btn-new-chat').on('click', newSession);
    $('#btn-theme').on('click', toggleTheme);
    $('#btn-toggle-sidebar').on('click', function () {
      $('#ai-sidebar').toggleClass('collapsed');
    });

    $('.ai-suggestions').on('click', '.ai-suggestion', function () {
      $('#message-input').val($(this).data('msg'));
      sendMessage();
    });

    $('#btn-attach').on('click', function () { $('#file-input').click(); });
    $('#file-input').on('change', function () {
      if (this.files[0]) uploadFile(this.files[0]);
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

    $('#messages').on('click', '.ai-option', function () {
      sendMessage($(this).text().trim());
    });

    $('#messages').on('click', '.ai-btn-yes', function () {
      var action = $(this).data('action');
      var params = $(this).data('params') || {};
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
    });
  }

  function initDragDrop() {
    var dropzone = $('#dropzone');
    var inputArea = $('.ai-input-area');

    inputArea.on('dragover dragenter', function (e) {
      e.preventDefault();
      dropzone.addClass('active');
    });

    inputArea.on('dragleave drop', function (e) {
      e.preventDefault();
      dropzone.removeClass('active');
    });

    inputArea.on('drop', function (e) {
      var files = e.originalEvent.dataTransfer.files;
      if (files.length) uploadFile(files[0]);
    });
  }

  function sendMessage(overrideMessage, selectionId, selectionType) {
    if (isLoading || !cfg().api_configured) return;

    var message = (overrideMessage || $('#message-input').val()).trim();
    if (!message) return;

    $('.ai-welcome').remove();
    appendMessage('user', message);
    $('#message-input').val('').css('height', 'auto');

    isLoading = true;
    $('#btn-send').prop('disabled', true);
    $('#typing-indicator').prop('hidden', false);

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
        $('#btn-send').prop('disabled', false);
        $('#typing-indicator').prop('hidden', true);
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

    var $msg = appendMessage('assistant', json.message || '');

    if (json.ui) renderUI($msg, json.ui);

    if (json.needs_confirmation && json.ui) {
      renderConfirm($msg, json);
    }

    if (json.preview) {
      $msg.find('.ai-message-body').append(
        '<div class="ai-preview"><img src="' + json.preview + '" alt="Preview"/></div>'
      );
    }
  }

  function renderUI($msg, ui) {
    var $body = $msg.find('.ai-message-body');
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
        html += '<button class="ai-btn-yes" data-action="' + esc(ui.action || '') + '" data-params=\'' + JSON.stringify(ui.params || {}) + '\'>Yes</button>';
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
    $('#typing-indicator').prop('hidden', false);

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
        $('#typing-indicator').prop('hidden', true);
      }
    });
  }

  function uploadFile(file) {
    var formData = new FormData();
    formData.append('file', file);
    formData.append('session_id', sessionId);

    isLoading = true;
    $('#typing-indicator').prop('hidden', false);
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
      error: function () {
        appendMessage('assistant', 'Upload failed. Please try again.');
      },
      complete: function () {
        isLoading = false;
        $('#typing-indicator').prop('hidden', true);
        $('#file-input').val('');
      }
    });
  }

  function appendMessage(role, content) {
    var icon = role === 'user' ? 'fa-user' : 'fa-robot';
    var $msg = $(
      '<div class="ai-message ' + role + '">' +
        '<div class="ai-message-avatar"><i class="fa-solid ' + icon + '"></i></div>' +
        '<div class="ai-message-body"><pre>' + esc(content) + '</pre></div>' +
      '</div>'
    );
    $('#messages').append($msg);
    scrollToBottom();
    return $msg;
  }

  function newSession() {
    $.post(cfg().url_new_session, function (json) {
      sessionId = json.session_id || 0;
      $('#messages').html(
        '<div class="ai-welcome">' +
          '<div class="ai-welcome-icon"><i class="fa-solid fa-robot"></i></div>' +
          '<h2>Website Builder Assistant</h2>' +
          '<p>Start a new conversation.</p>' +
        '</div>'
      );
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
    updateThemeIcon(next);
  }

  function scrollToBottom() {
    var el = document.getElementById('messages');
    el.scrollTop = el.scrollHeight;
  }

  function autoResize() {
    $('#message-input').on('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
  }

  function esc(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }
})(jQuery);
