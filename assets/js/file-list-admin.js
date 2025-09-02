/* BF Secret File Downloader - Admin File List JS */
/* global jQuery, bfFileListData */
(function($){
  if (typeof bfFileListData === 'undefined') return;

  // Template getter for auth details
  function getAuthDetailsTemplate(){
    var tpl = document.getElementById('bf-auth-details-template');
    if (tpl && tpl.content) {
      // Return a DOM node (cloned) so callers can append directly
      return document.importNode(tpl.content, true);
    }
    // Fallback for older browsers: use innerHTML string
    var $fallback = $('#bf-auth-details-template');
    return $fallback.length ? $fallback.html() : '';
  }

  function checkDashicons() {

    // Check if Dashicons font is loaded
    var testElement = $('<span class="dashicons dashicons-folder" style="font-family: dashicons; position: absolute; left: -9999px;"></span>');
    $('body').append(testElement);

    // Check if the font is loaded
    setTimeout(function() {
        var computedStyle = window.getComputedStyle(testElement[0]);
        var fontFamily = computedStyle.getPropertyValue('font-family');

        if (fontFamily.indexOf('dashicons') !== -1) {
            console.log('Dashiconsが利用可能です - Dashiconsを表示します');
            // If Dashicons is loaded, display Dashicons and hide the fallback
            $('.dashicons').css('display', 'inline-block !important').show();
            $('.bf-fallback-icon').hide();

            // Additional style forced application
            $('.bf-directory-icon').css({
                'display': 'inline-block',
                'font-family': 'dashicons',
                'font-size': '20px',
                'margin-right': '8px',
                'vertical-align': 'middle'
            });

            $('.bf-file-icon').css({
                'display': 'inline-block',
                'font-family': 'dashicons',
                'font-size': '16px',
                'margin-right': '8px',
                'vertical-align': 'middle'
            });

        } else {
            console.log('Dashiconsが利用できません。フォールバックアイコンを使用します');
            $('.dashicons').hide();
            $('.bf-fallback-icon').show();
        }

        testElement.remove();
    }, 1000);
  }

  // Initialize authentication details display on page load
  function initializeAuthDetails() {
    var currentPath = $('#current-path').val();
    var hasAuth = checkCurrentDirectoryHasAuth();

    if (hasAuth && currentPath) {
        // Check if authentication details are already displayed
        var authDetails = $('.bf-auth-details');
        if (authDetails.length === 0) {
            $('.bf-path-info').append(getAuthDetailsTemplate());
        }

        // Load and display authentication settings
        loadDirectoryAuthSettings(currentPath);
    }
  }

  // Check if the file is a program code file
  function isProgramCodeFile(filename) {
    // Program code file extension list
    var codeExtensions = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phps',
        'js', 'jsx', 'ts', 'tsx',
        'css', 'scss', 'sass', 'less',
        'html', 'htm', 'xhtml',
        'xml', 'xsl', 'xslt',
        'json', 'yaml', 'yml',
        'py', 'pyc', 'pyo',
        'rb', 'rbw',
        'pl', 'pm',
        'java', 'class', 'jar',
        'c', 'cpp', 'cc', 'cxx', 'h', 'hpp',
        'cs', 'vb', 'vbs',
        'sh', 'bash', 'zsh', 'fish',
        'sql', 'mysql', 'pgsql',
        'asp', 'aspx', 'jsp',
        'cgi', 'fcgi'
    ];

    // Configuration files and dangerous files
    var configFiles = [
        '.htaccess', '.htpasswd', '.env', '.ini',
        'web.config', 'composer.json', 'package.json',
        'Dockerfile', 'docker-compose.yml',
        'Makefile', 'CMakeLists.txt'
    ];

    // Check by extension
    var extension = filename.split('.').pop().toLowerCase();
    if (codeExtensions.includes(extension)) {
        return true;
    }

    // Check by filename
    if (configFiles.includes(filename)) {
        return true;
    }

    // Script file names often used without extension
    var scriptNames = [
        'index', 'config', 'settings', 'install', 'setup',
        'admin', 'login', 'auth', 'database', 'db'
    ];

    var basename = filename.split('.')[0].toLowerCase();
    if (scriptNames.includes(basename) && !filename.includes('.')) {
        return true;
    }

    return false;
  }

  function getCurrentSortBy() {
      return $('.sortable.sorted').length > 0 ?
          $('.sortable.sorted').find('.sort-link').data('sort') : 'name';
  }

  function getCurrentSortOrder() {
      if ($('.sortable.sorted.asc').length > 0) return 'asc';
      if ($('.sortable.sorted.desc').length > 0) return 'desc';
      return 'asc';
  }

  function navigateToDirectoryWithSort(path, page, sortBy, sortOrder) {
    $('#bf-secret-file-downloader-loading').show();

    var i18n = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'bf_sfd_browse_files',
            path: path,
            page: page,
            sort_by: sortBy,
            sort_order: sortOrder,
            nonce: bfFileListData.nonce
        },
        success: function(response) {
            if (response.success) {
                updateFileListWithSort(response.data, sortBy, sortOrder);
                // Update URL (add to browser history)
                var newUrl = new URL(window.location);
                newUrl.searchParams.set('path', path);
                newUrl.searchParams.set('paged', page);
                newUrl.searchParams.set('sort_by', sortBy);
                newUrl.searchParams.set('sort_order', sortOrder);
                window.history.pushState({path: path, page: page, sortBy: sortBy, sortOrder: sortOrder}, '', newUrl);
            } else {
                // If the directory cannot be accessed, try to move to the parent directory
                var errorCode = response.data.error_code;
                var errorMessage = response.data.message || (i18n.anErrorOccurred || 'An error occurred');

                // Check if the error is a directory access error based on error code
                if (errorCode === 'ACCESS_DENIED' || errorCode === 'DIRECTORY_NOT_FOUND') {
                    // If the directory access error occurs, try to move to the parent directory
                    var parentPath = getParentPath(path);
                    if (parentPath !== path) {
                        navigateToDirectoryWithSort(parentPath, 1, sortBy, sortOrder);
                        return;
                    }
                }

                alert(errorMessage);
            }
        },
        error: function() {
            alert(i18n.communicationErrorOccurred || 'Communication error occurred');
        },
        complete: function() {
            $('#bf-secret-file-downloader-loading').hide();
        }
    });
  }

  function updateFileListWithSort(data, sortBy, sortOrder) {
    // Update sort state
    $('.sortable').removeClass('sorted asc desc');
    $('.sortable').each(function() {
        var linkSortBy = $(this).find('.sort-link').data('sort');
        if (linkSortBy === sortBy) {
            $(this).addClass('sorted ' + sortOrder);
        }
    });

    updateFileList(data);
  }

  // Check if the current directory has authentication settings
  function checkCurrentDirectoryHasAuth() {
    var indicator = $('.bf-auth-indicator');
    if (indicator.length === 0) {
        return false;
    }

    // Check the indicator text to determine if there are directory-specific settings
    var statusText = indicator.find('.bf-auth-status-text').text();
    var hasAuthDetails = $('.bf-auth-details').length > 0;

    return statusText.includes('ディレクトリ毎認証設定あり') || hasAuthDetails;
  }

  function updateFileList(data) {
    // Update current path
    $('#current-path').val(data.current_path);
    var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
    $('#current-path-display').text(data.current_path || (strings.rootDirectory || 'Root directory'));

    // Rebuild the entire path display area
    $('.bf-secret-file-downloader-path').html(createPathDisplayTemplate(data));

    // Update authentication indicator (after updating the path display area)
    var hasAuth = data.current_directory_has_auth || false;
    updateAuthIndicator(hasAuth);

    // Reset event handlers
    $('#go-up-btn').on('click', function(e) {
        e.preventDefault();
        var currentPath = $('#current-path').val();
        if (currentPath) {
            var parentPath = getParentPath(currentPath);
            navigateToDirectory(parentPath, 1);
        }
    });

    $('#directory-auth-btn').on('click', function(e) {
        e.preventDefault();
        openDirectoryAuthModal();
    });

    // Update statistics
    $('.bf-secret-file-downloader-stats p').text(
        data.total_items > 0
            ? (strings.itemsFound || '%d items found.').replace('%d', data.total_items)
            : (strings.noItemsFound || 'No items found.')
    );

    // Update file list
    var tbody = $('#file-list-tbody');
    tbody.empty();

    if (data.items && data.items.length > 0) {
        $.each(data.items, function(index, file) {
            tbody.append(createFileRow(file));
        });

        // Stop event propagation for dynamically generated checkboxes
        $('input[name="file_paths[]"]').off('click').on('click', function(e) {
            e.stopPropagation();
        });

        // Stop event propagation for dynamically generated checkbox labels
        $('.check-column label').off('click').on('click', function(e) {
            e.stopPropagation();
        });
    } else {
        var strings2 = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
        tbody.append(
            '<tr><td colspan="5" style="text-align: center; padding: 40px;">' +
            (strings2.noFilesFound || 'No files or directories found.') +
            '</td></tr>'
        );
    }

    // Update pagination
    updatePagination(data);
  }

  function updatePagination(data) {

    const strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
    // Remove existing pagination elements
    $('.tablenav').remove();

    // Generate pagination HTML
    var paginationHtml = generatePaginationHtml(data.current_page, data.total_pages, data.current_path);

    // Update top tablenav template with data
    var topTablenav = $('#top-tablenav-template').html();

    // Determine capability flags with robust fallbacks
    var canDelete = (typeof data.current_user_can_delete !== 'undefined')
        ? !!data.current_user_can_delete
        : (typeof bfFileListData !== 'undefined' && bfFileListData.initialData && !!bfFileListData.initialData.current_user_can_delete);

    // Replace placeholders with actual data
    topTablenav = topTablenav
        .replace(/\{\{DELETE_OPTION\}\}/g, canDelete ? '<option value="delete">' + (strings.delete || 'Delete') + '</option>' : '')
        .replace(/\{\{PAGINATION_SECTION\}\}/g, data.total_pages > 1 ? '<div class="tablenav-pages">' + paginationHtml + '</div>' : '');

    // Place top tablenav before the table
    $('.bf-secret-file-downloader-file-table').before(topTablenav);

    // If there is pagination, add bottom tablenav
    if (data.total_pages > 1) {
        var bottomTablenav = $('#bottom-tablenav-template').html()
            .replace(/\{\{PAGINATION_LINKS\}\}/g, paginationHtml);

        $('.bf-secret-file-downloader-file-table').after(bottomTablenav);
    }
  }

  function createPathDisplayTemplate(data) {
    var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
    var template = $('#path-display-template').html();

    // Prepare data for template (use localized strings)
    var currentDirectoryLabel = strings.currentDirectory || 'Current directory:';
    var currentPathDisplay = data.current_path || (strings.rootDirectory || 'Root directory');
    var currentPathValue = data.current_path || '';

    // Generate go up button
    var goUpButton = '';
    if (data.current_path && data.current_path !== '') {
        goUpButton = '<button type="button" id="go-up-btn" class="button button-small">' +
            '<span class="dashicons dashicons-arrow-up-alt2"></span>' +
            (strings.goUp || 'Go to parent directory') +
            '</button>';
    }

    // Generate auth settings button (based on permission flag, with fallback)
    var authSettingsButton = '';
    var canManageAuth = (typeof data.current_user_can_manage_auth !== 'undefined')
        ? !!data.current_user_can_manage_auth
        : (typeof bfFileListData !== 'undefined' && bfFileListData.initialData && !!bfFileListData.initialData.current_user_can_manage_auth);
    if (canManageAuth && data.current_path && data.current_path !== '') {
        authSettingsButton = '<button type="button" id="directory-auth-btn" class="button button-small">' +
            '<span class="dashicons dashicons-admin-users"></span>' +
            (strings.authSettings || 'Authentication settings') +
            '</button>';
    }

    // Replace template placeholders
    return template
        .replace(/\{\{CURRENT_DIRECTORY_LABEL\}\}/g, currentDirectoryLabel)
        .replace(/\{\{CURRENT_PATH_DISPLAY\}\}/g, currentPathDisplay)
        .replace(/\{\{CURRENT_PATH_VALUE\}\}/g, currentPathValue)
        .replace(/\{\{GO_UP_BUTTON\}\}/g, goUpButton)
        .replace(/\{\{AUTH_SETTINGS_BUTTON\}\}/g, authSettingsButton);
  }
  // Update the authentication setting indicator
  function updateAuthIndicator(hasAuth) {
    var indicator = $('.bf-auth-indicator');
    var authDetails = $('.bf-auth-details');
    var currentPath = $('#current-path').val();
    var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
    var targetText = strings.targetDirectorySettings || 'Target directory settings';
    var commonText = strings.commonAuthApplied || 'Common authentication settings applied';

    if (hasAuth) {
        if (indicator.length === 0) {
            $('.bf-path-info').append('<span class="bf-auth-indicator"><span class="dashicons dashicons-lock"></span><span class="bf-auth-status-text">' + targetText + '</span></span>');
        } else {
            // Update the existing indicator
            indicator.html('<span class="dashicons dashicons-lock"></span><span class="bf-auth-status-text">' + targetText + '</span>');
            indicator.css('color', '');
        }

        // Display authentication details
        if (authDetails.length === 0) {
            $('.bf-path-info').append(getAuthDetailsTemplate());
        }

        // Display authentication details
        loadDirectoryAuthSettings(currentPath);
    } else {
        // If there are no directory-specific settings, display "Common authentication settings applied"
        if (indicator.length === 0) {
            $('.bf-path-info').append('<span class="bf-auth-indicator" style="color: #666;"><span class="dashicons dashicons-admin-users"></span><span class="bf-auth-status-text">' + commonText + '</span></span>');
        } else {
            indicator.html('<span class="dashicons dashicons-admin-users"></span><span class="bf-auth-status-text">' + commonText + '</span>');
            indicator.css('color', '#666');
        }
        authDetails.remove();
    }
  }
  function createFileRow(file) {
    var row = $('<tr></tr>')
        .attr('data-path', file.path)
        .attr('data-type', file.type);

    if (file.type === 'directory' && file.readable) {
        row.addClass('clickable-directory').css('cursor', 'pointer');
    }

    // Checkbox column
    var checkboxCell = $('<th scope="row" class="check-column"></th>');
    var checkbox = $('<input type="checkbox" name="file_paths[]">')
        .attr('value', file.path)
        .attr('data-file-name', file.name)
        .attr('data-file-type', file.type);
    checkboxCell.append(checkbox);

    var nameCell = createNameCell(file);

    var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
    var typeCell = $('<td class="column-type"></td>').text(
        file.type === 'directory'
            ? (strings.directory || 'Directory')
            : (strings.file || 'File')
    );

    var sizeCell = $('<td class="column-size"></td>').text(
        file.size === '-' ? '-' : formatFileSize(file.size)
    );

    var modifiedCell = $('<td class="column-modified"></td>').text(
        new Date(file.modified * 1000).toLocaleString('ja-JP')
    );

    row.append(checkboxCell, nameCell, typeCell, sizeCell, modifiedCell);
    return row;
  }

  function createNameCell(file) {
    var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
    var nameCell = $('<td class="column-name has-row-actions"></td>');
    var iconWrapper = createIconWrapper(file);
    var rowActions = createRowActions(file);

    if (file.type === 'directory') {
        if (file.readable) {
            nameCell.html(iconWrapper + '<strong class="bf-directory-name row-title"><a href="#" class="open-directory" data-path="' + $('<div>').text(file.path).html() + '">' + $('<div>').text(file.name).html() + '</a></strong>');
        } else {
            nameCell.html(iconWrapper + '<span class="bf-directory-name-disabled row-title">' + $('<div>').text(file.name).html() + '</span>' +
                         '<small class="bf-access-denied">(' + (strings.accessDenied || 'Access denied') + ')</small>');
        }
    } else {
        nameCell.html(iconWrapper + '<span class="bf-file-name row-title"><a href="#" class="download-file-link" data-file-path="' + $('<div>').text(file.path).html() + '" data-file-name="' + $('<div>').text(file.name).html() + '">' + $('<div>').text(file.name).html() + '</a></span>');
    }

    nameCell.append(rowActions);
    return nameCell;
  }
   // Template function group
   function createIconWrapper(file) {
    if (file.type === 'directory') {
        return '<span class="bf-icon-wrapper">' +
            '<span class="dashicons dashicons-folder bf-directory-icon" style="font-size: 20px !important; margin-right: 8px; vertical-align: middle; font-family: dashicons !important;"></span>' +
            '<span class="bf-fallback-icon" style="display: none; font-size: 18px; margin-right: 8px; vertical-align: middle;">📁</span>' +
            '</span>';
    } else {
        var iconClass = file.type_class || '';
        var fallbackEmoji = '📄';

        if (iconClass === 'image-file') {
            fallbackEmoji = '🖼️';
        } else if (iconClass === 'document-file') {
            fallbackEmoji = '📝';
        } else if (iconClass === 'archive-file') {
            fallbackEmoji = '📦';
        }

        return '<span class="bf-icon-wrapper">' +
            '<span class="dashicons dashicons-media-default bf-file-icon" style="font-size: 16px !important; margin-right: 8px; vertical-align: middle; font-family: dashicons !important;"></span>' +
            '<span class="bf-fallback-icon" style="display: none; font-size: 16px; margin-right: 8px; vertical-align: middle;">' + fallbackEmoji + '</span>' +
            '</span>';
    }
  }

  function createRowActions(file) {
    var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
    var rowActions = '<div class="row-actions">';

    if (file.type === 'directory') {
        if (file.readable) {
            rowActions += '<span class="open"><a href="#" class="open-directory" data-path="' + $('<div>').text(file.path).html() + '">' + (strings.open || 'Open') + '</a>';

            if (file.can_delete) {
                rowActions += ' | ';
            }
            rowActions += '</span>';
        }
    } else {
        rowActions += '<span class="download"><a href="#" class="download-file-link" ' +
            'data-file-path="' + $('<div>').text(file.path).html() + '" ' +
            'data-file-name="' + $('<div>').text(file.name).html() + '">' + (strings.download || 'Download') + '</a> | </span>';
        rowActions += '<span class="copy-url"><a href="#" class="copy-url-link" ' +
            'data-file-path="' + $('<div>').text(file.path).html() + '" ' +
            'data-file-name="' + $('<div>').text(file.name).html() + '">' + (strings.copyUrl || 'Copy URL') + '</a>';

        if (file.can_delete) {
            rowActions += ' | ';
        }
        rowActions += '</span>';
    }

    if (file.can_delete) {
        rowActions += '<span class="delete"><a href="#" class="delete-file-link" ' +
            'data-file-path="' + $('<div>').text(file.path).html() + '" ' +
            'data-file-name="' + $('<div>').text(file.name).html() + '" ' +
            'data-file-type="' + $('<div>').text(file.type).html() + '">' + (strings.delete || 'Delete') + '</a></span>';
    }

    rowActions += '</div>';
    return rowActions;
  }

  function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    var k = 1024;
    var sizes = ['B', 'KB', 'MB', 'GB'];
    var i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  function generatePaginationHtml(currentPage, totalPages, currentPath) {
    var template = $('#pagination-template').html();
    var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};

    // Generate previous link
    var previousLink = '';
    if (currentPage > 1) {
        previousLink = '<a href="?page=bf-secret-file-downloader&path=' + encodeURIComponent(currentPath) + '&paged=' + (currentPage - 1) + '">&laquo; ' + (strings.previous || 'Previous') + '</a>';
    }

    // Generate page numbers
    var pageNumbers = '';
    var startPage = Math.max(1, currentPage - 2);
    var endPage = Math.min(totalPages, currentPage + 2);

    for (var i = startPage; i <= endPage; i++) {
        if (i == currentPage) {
            pageNumbers += '<span class="current">' + i + '</span>';
        } else {
            pageNumbers += '<a href="?page=bf-secret-file-downloader&path=' + encodeURIComponent(currentPath) + '&paged=' + i + '">' + i + '</a>';
        }
    }

    // Generate next link
    var nextLink = '';
    if (currentPage < totalPages) {
        nextLink = '<a href="?page=bf-secret-file-downloader&path=' + encodeURIComponent(currentPath) + '&paged=' + (currentPage + 1) + '">' + (strings.next || 'Next') + ' &raquo;</a>';
    }

    // Replace template placeholders
    return template
        .replace(/\{\{PREVIOUS_LINK\}\}/g, previousLink)
        .replace(/\{\{PAGE_NUMBERS\}\}/g, pageNumbers)
        .replace(/\{\{NEXT_LINK\}\}/g, nextLink);
  }
   // Load the directory authentication settings
   function loadDirectoryAuthSettings(currentPath) {
    var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'bf_sfd_get_directory_auth',
            path: currentPath,
            nonce: typeof bfFileListData !== 'undefined' && bfFileListData.nonce ? bfFileListData.nonce : ''
        },
        success: function(response) {
            if (response.success) {
                var authSettings = response.data;

                // Authentication method settings
                $('#bf-auth-methods-logged-in').prop('checked', authSettings.auth_methods.includes('logged_in'));
                $('#bf-auth-methods-simple-auth').prop('checked', authSettings.auth_methods.includes('simple_auth'));

                // Allowed role settings
                $('input[name="bf_allowed_roles[]"]').prop('checked', false);
                if (authSettings.allowed_roles) {
                    authSettings.allowed_roles.forEach(function(role) {
                        $('#bf-allowed-roles-' + role).prop('checked', true);
                    });
                }

                // Simple authentication password settings
                if (authSettings.simple_auth_password) {
                    $('#bf-simple-auth-password').val(authSettings.simple_auth_password);
                }

                // Display/hide simple authentication password section
                if (authSettings.auth_methods.includes('simple_auth')) {
                    $('#bf-simple-auth-password-section').show();
                } else {
                    $('#bf-simple-auth-password-section').hide();
                }

                // Display/hide role selection section
                if (authSettings.auth_methods.includes('logged_in')) {
                    $('#bf-allowed-roles-section').show();
                } else {
                    $('#bf-allowed-roles-section').hide();
                }

                // Display authentication details
                displayAuthDetails(authSettings);
            }
        },
        error: function() {
            alert(strings.failedToRetrieveAuth || 'Failed to retrieve authentication settings.');
        }
    });
  }

   // Display authentication details
   function displayAuthDetails(authSettings) {
    var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
    var detailsHtml = '<div class="auth-details-list">';

    // Display authentication method
    detailsHtml += '<div class="auth-detail-item"><strong>' + (strings.authenticationMethod || 'Authentication method:') + '</strong> ';
    var authMethods = [];
    if (authSettings.auth_methods.includes('logged_in')) {
        authMethods.push(strings.loginUser || 'Login user');
    }
    if (authSettings.auth_methods.includes('simple_auth')) {
        authMethods.push(strings.simpleAuth || 'Simple authentication');
    }
    detailsHtml += authMethods.join(', ') + '</div>';

    // Display allowed roles
    if (authSettings.allowed_roles && authSettings.allowed_roles.length > 0) {
        detailsHtml += '<div class="auth-detail-item"><strong>' + (strings.allowedRoles || 'Allowed roles:') + '</strong> ';
        var roleLabels = {
            'administrator': strings.administrator || 'Administrator',
            'editor': strings.editor || 'Editor',
            'author': strings.author || 'Author',
            'contributor': strings.contributor || 'Contributor',
            'subscriber': strings.subscriber || 'Subscriber'
        };
        var roles = authSettings.allowed_roles.map(function(role) {
            return roleLabels[role] || role;
        });
        detailsHtml += roles.join(', ') + '</div>';
    }

    // Display simple authentication password
    if (authSettings.auth_methods.includes('simple_auth') && authSettings.simple_auth_password) {
        detailsHtml += '<div class="auth-detail-item"><strong>' + (strings.simpleAuthPassword || 'Simple authentication password:') + '</strong> ';
        detailsHtml += '••••••••</div>';
    }

    detailsHtml += '</div>';
    $('#auth-details-content').html(detailsHtml);
  }

  function getParentPath(currentPath) {
    if (!currentPath || currentPath === '') {
        return '';
    }

    // Split path by separator
    var parts = currentPath.split('/').filter(function(part) {
        return part !== '';
    });

    // Remove the last part
    parts.pop();

    // Rebuild the parent path
    return parts.join('/');
  }

  function navigateToDirectory(path, page) {
    var currentSortBy = getCurrentSortBy();
    var currentSortOrder = getCurrentSortOrder();
    navigateToDirectoryWithSort(path, page, currentSortBy, currentSortOrder);
  }


  function uploadFiles(files) {
    var currentPath = $('#current-path').val();

    // Relative path is OK even if it is empty (root directory)
    var init = (typeof bfFileListData !== 'undefined' && bfFileListData.initialData) ? bfFileListData.initialData : {};
    var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
    var maxMb = init.max_file_size_mb || 10;
    var maxFileSize = maxMb * 1024 * 1024; // MB to bytes
    var uploadedCount = 0;
    var totalFiles = files.length;
    var errors = [];

    $('#upload-progress').show();
    updateUploadProgress(0, (strings.startingUpload || 'Starting upload...'));

    // Upload each file in order
    function uploadNextFile(index) {
      var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
      if (index >= totalFiles) {
          // All uploads are complete
          $('#upload-progress').hide();

          if (errors.length > 0) {
              var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
              alert((strings.errorsOccurredWithSomeFiles || 'Errors occurred with some files:') + '\n' + errors.join('\n'));
          } else {
              // Show success message
              var strings2 = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
              showSuccessMessage(uploadedCount + (strings2.filesUploadedSuffix || 'files uploaded.'));
          }

          // Update file list
          navigateToDirectory(currentPath, 1);
          return;
      }

      var file = files[index];
      var fileName = file.name;

      // File size check
      if (file.size > maxFileSize) {
          errors.push(fileName + ': ' + (strings.fileSizeExceedsLimit || 'File size exceeds limit'));
          uploadNextFile(index + 1);
          return;
      }

      // Program code file check
      if (isProgramCodeFile(fileName)) {
          errors.push(fileName + ': ' + (strings.cannotUploadForSecurity || 'Cannot upload for security reasons'));
          uploadNextFile(index + 1);
          return;
      }

      // Create FormData
      var formData = new FormData();
      formData.append('action', 'bf_sfd_upload_file');
      formData.append('target_path', currentPath);
      formData.append('file', file);
      formData.append('nonce', (typeof bfFileListData !== 'undefined' && bfFileListData.nonce) ? bfFileListData.nonce : '');

      // Update upload progress
      var progress = Math.round(((index + 1) / totalFiles) * 100);
      updateUploadProgress(progress, (strings.uploading || 'Uploading:') + ' ' + fileName);

      // Send AJAX
      $.ajax({
          url: ajaxurl,
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          success: function(response) {
              if (response.success) {
                  uploadedCount++;
              } else {
                  errors.push(fileName + ': ' + (response.data || (strings.uploadFailed || 'Upload failed')));
              }
              uploadNextFile(index + 1);
          },
          error: function() {
              errors.push(fileName + ': ' + (strings.communicationErrorOccurred || 'Communication error occurred'));
              uploadNextFile(index + 1);
          }
      });
    }

    // Start upload
    uploadNextFile(0);
  }

  function updateUploadProgress(percent, message) {
    $('.upload-progress-fill').css('width', percent + '%');
    $('#upload-status').text(message);
  }

  function showSuccessMessage(message) {
    // Show success message (simplified version)
    $('<div class="notice notice-success is-dismissible" style="margin: 20px 0;"><p>' + message + '</p></div>')
        .insertAfter('.bf-secret-file-downloader-header')
        .delay(5000)
        .fadeOut();
  }

  function downloadFile(filePath, fileName) {
    var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
    var nonce = (typeof bfFileListData !== 'undefined' && bfFileListData.nonce) ? bfFileListData.nonce : '';
    if (!filePath) {
        alert(strings.invalidFilePath || 'Invalid file path.');
        return;
    }

    // Message for starting download process
    showSuccessMessage(strings.preparingDownload || 'Preparing download...');

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'bf_sfd_download_file',
            file_path: filePath,
            nonce: nonce
        },
        success: function(response) {
            if (response.success && response.data.download_url) {
                // Create a hidden link for download
                var link = document.createElement('a');
                link.href = response.data.download_url;
                link.download = response.data.filename || fileName;
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                showSuccessMessage(strings.downloadStarted || 'Download started.');
            } else {
                alert(response.data || (strings.downloadFailed || 'Download failed.'));
            }
        },
        error: function() {
            alert(strings.communicationErrorOccurred || 'Communication error occurred.');
        }
    });
  }

  function createDirectory() {
    var currentPath = $('#current-path').val();
    var directoryName = $('#directory-name-input').val().trim();
    var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};

    // Relative path is OK even if it is empty (root directory)
    if (!directoryName) {
        alert(strings.pleaseEnterDirectoryName || 'Please enter directory name.');
        $('#directory-name-input').focus();
        return;
    }

    // Directory name validation
    var validPattern = /^[a-zA-Z0-9_\-\.]+$/;
    if (!validPattern.test(directoryName)) {
        alert(strings.directoryNameInvalid || 'Directory name contains invalid characters. Only alphanumeric characters, underscores, hyphens, and dots are allowed.');
        $('#directory-name-input').focus();
        return;
    }

    // Check if the directory name starts with a dot
    if (directoryName.charAt(0) === '.') {
        alert(strings.cannotCreateDotDirectory || 'Cannot create directory names starting with a dot.');
        $('#directory-name-input').focus();
        return;
    }

    // Disable button
    $('#create-directory-submit').prop('disabled', true).text(strings.creating || 'Creating...');

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'bf_sfd_create_directory',
            parent_path: currentPath,
            directory_name: directoryName,
            nonce: (typeof bfFileListData !== 'undefined' && bfFileListData.nonce) ? bfFileListData.nonce : ''
        },
        success: function(response) {
            if (response.success) {
                showSuccessMessage(response.data.message);
                $('#create-directory-form').slideUp();
                $('#directory-name-input').val('');

                // Update file list
                navigateToDirectory(currentPath, 1);
            } else {
                alert(response.data || (strings.failedToCreateDirectory || 'Failed to create directory.'));
            }
        },
        error: function() {
            alert(strings.communicationErrorOccurred || 'Communication error occurred.');
        },
        complete: function() {
            // Enable button
            $('#create-directory-submit').prop('disabled', false).text(strings.create || 'Create');
        }
    });
  }

  function deleteFile(filePath, fileName, fileType) {
    var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
    var confirmMessage = fileType === 'directory'
        ? (strings.deleteDirectoryConfirm || "Delete directory '%s' and all its contents? This action cannot be undone.")
        : (strings.deleteFileConfirm || "Delete file '%s'? This action cannot be undone.");

    if (!confirm(confirmMessage.replace('%s', fileName))) {
        return;
    }

    // Update the display during the deletion process
    var deleteLink = $('a[data-file-path="' + filePath + '"].delete-file-link');
    var originalText = deleteLink.text();
    deleteLink.text(strings.deleting || 'Deleting...').prop('disabled', true).css('color', '#999');

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'bf_sfd_delete_file',
            file_path: filePath,
            nonce: (typeof bfFileListData !== 'undefined' && bfFileListData.nonce) ? bfFileListData.nonce : ''
        },
        success: function(response) {
            if (response.success) {
                showSuccessMessage(response.data.message);

                // Move to the appropriate directory after deletion
                var currentPath = $('#current-path').val();
                var targetPath = currentPath;
                var deletedPath = response.data.deleted_path;

                // Check if the deleted item is a directory and if the current path is within the deleted directory
                if (fileType === 'directory') {
                    // Compare the deleted directory path with the current path
                    if (currentPath === deletedPath ||
                        (currentPath && deletedPath && currentPath.indexOf(deletedPath + '/') === 0)) {
                        // If the current path is within the deleted directory or one of its subdirectories,
                        // move to the parent path returned by the server
                        targetPath = response.data.parent_path || '';
                     }
                }

                // Update file list
                navigateToDirectory(targetPath, 1);
            } else {
                var errorMsg = response.data || (strings.failedToDeleteFile || 'Failed to delete file.');
                 alert(errorMsg);

                // Restore the deleted button
                deleteLink.text(originalText).prop('disabled', false).css('color', '');
            }
        },
        error: function(xhr, status, error) {
             alert(strings.communicationErrorDuringDeletion || 'Communication error occurred during deletion. Please try again.');

            // Restore the deleted button when an error occurs
            deleteLink.text(originalText).prop('disabled', false).css('color', '');
        }
    });
  }

  function bulkDeleteFiles() {
    var checkedFiles = $('input[name="file_paths[]"]:checked');
    var filePaths = [];
    var fileNames = [];
    var hasDirectories = false;

    checkedFiles.each(function() {
        filePaths.push($(this).val());
        fileNames.push($(this).data('file-name'));
        if ($(this).data('file-type') === 'directory') {
            hasDirectories = true;
        }
    });

    // Confirm message
    var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
    var confirmMessage;
    if (hasDirectories) {
        confirmMessage = strings.bulkDeleteConfirmWithDirs || 'Delete %d selected items (including directories) and all their contents? This action cannot be undone.';
    } else {
        confirmMessage = strings.bulkDeleteConfirm || 'Delete %d selected items? This action cannot be undone.';
    }

    if (!confirm(confirmMessage.replace('%d', filePaths.length))) {
        return;
    }

    // Disable the bulk delete button
    $('#doaction').prop('disabled', true).val(strings.deleting || 'Deleting...');

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'bf_sfd_bulk_delete',
            file_paths: filePaths,
            current_path: $('#current-path').val(),
            nonce: (typeof bfFileListData !== 'undefined' && bfFileListData.nonce) ? bfFileListData.nonce : ''
        },
        success: function(response) {
            if (response.success) {
                showSuccessMessage(response.data.message);

                // Processing when the current path is deleted
                var targetPath = $('#current-path').val();
                if (response.data.current_path_deleted && response.data.redirect_path !== undefined) {
                    targetPath = response.data.redirect_path;
                }

                // Update file list
                navigateToDirectory(targetPath, 1);
            } else {
                var errorMsg = response.data || (strings.bulkDeleteFailed || 'Bulk delete failed.');
                alert(errorMsg);
            }
        },
        error: function(xhr, status, error) {
             alert(strings.communicationErrorBulkDeletion || 'Communication error occurred during bulk deletion. Please try again.');
        },
        complete: function() {
            // Enable button
            $('#doaction').prop('disabled', false).val(strings.apply || 'Apply');

            // Clear checkboxes
            $('input[name="file_paths[]"]').prop('checked', false);
            $('#cb-select-all-1').prop('checked', false);
        }
    });
  }

    // Open the directory authentication modal
    function openDirectoryAuthModal() {
      var currentPath = $('#current-path').val();
      var currentPathDisplay = $('#current-path-display').text();
      var hasAuth = checkCurrentDirectoryHasAuth();
      var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};

      // Update the modal title
      if (hasAuth) {
          $('#bf-auth-modal-title').text(strings.targetDirectorySettings || 'Target directory settings');
      } else {
          $('#bf-auth-modal-title').text(strings.directoryAuthSettings || 'Directory authentication settings');
      }

      // Update the current status display
      var statusIcon = $('.bf-auth-status-icon .dashicons');
      var statusDescription = $('#bf-auth-status-description');

      if (hasAuth) {
          statusIcon.removeClass('dashicons-unlock').addClass('dashicons-lock');
          statusIcon.css('color', '#0073aa');
          var hasSpecificText = (strings.dirHasSpecificAuth || 'This directory (%s) has directory-specific authentication settings.').replace('%s', currentPathDisplay);
          statusDescription.html(hasSpecificText);
          $('#bf-auth-modal-description').text(strings.changeDirSpecificSettings || "Change directory-specific settings or return to common settings using the 'Delete directory-specific settings' button below.");
          $('#bf-remove-auth').show();
          $('#bf-show-current-auth').show();
      } else {
          statusIcon.removeClass('dashicons-lock').addClass('dashicons-admin-users');
          statusIcon.css('color', '#666');
          var appliesCommonText = (strings.dirAppliesCommonAuth || 'This directory (%s) applies common authentication settings.').replace('%s', currentPathDisplay);
          statusDescription.html(appliesCommonText);
          $('#bf-auth-modal-description').text(strings.commonSettingsAppliedHint || 'Common settings are applied. To add directory-specific authentication settings, configure them below.');
          $('#bf-remove-auth').hide();
          $('#bf-show-current-auth').hide();
      }

      // Get authentication settings
      if (hasAuth) {
          loadDirectoryAuthSettings(currentPath);
      } else {
          // If no directory-specific settings, uncheck everything
          $('#bf-auth-methods-logged-in').prop('checked', false);
          $('#bf-auth-methods-simple-auth').prop('checked', false);
          $('input[name="bf_allowed_roles[]"]').prop('checked', false);
          $('#bf-simple-auth-password').val('');
          $('#bf-simple-auth-password-section').hide();
          $('#bf-allowed-roles-section').hide();
      }

      // Show the modal
      $('#bf-directory-auth-modal').fadeIn(300);
    }

    // Close the directory authentication modal
    function closeDirectoryAuthModal() {
        $('#bf-directory-auth-modal').fadeOut(300);
    }

   // Display the current password
   function showCurrentPassword() {
    var currentPath = $('#current-path').val();
    var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
    var nonce = (typeof bfFileListData !== 'undefined' && bfFileListData.nonce) ? bfFileListData.nonce : '';

    // Disable button
    $('#bf-show-current-password').prop('disabled', true).text(strings.retrieving || 'Retrieving...');

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'bf_sfd_get_directory_password',
            path: currentPath,
            nonce: nonce
        },
        success: function(response) {
            if (response.success) {
                alert((strings.currentPassword || 'Current password: ') + response.data.password);
            } else {
                alert(response.data || (strings.failedToRetrievePassword || 'Failed to retrieve password.'));
            }
        },
        error: function() {
            alert(strings.communicationErrorOccurred || 'Communication error occurred.');
        },
        complete: function() {
            // Enable button
            $('#bf-show-current-password').prop('disabled', false).text(strings.currentPassword || 'Current password');
        }
    });
  }


  // Open the URL copy modal
  function openUrlCopyModal(filePath, fileName) {
    // Update the modal elements
    $('#bf-url-file-name').text(fileName);

    // Save the file path in the modal
    $('#bf-url-copy-modal').data('file-path', filePath);

    // Select download by default
    $('input[name="url_type"][value="download"]').prop('checked', true);

    // Update URL
    updateUrlDisplay();

    // Show the modal
    $('#bf-url-copy-modal').fadeIn(300);
  }

  // Close the URL copy modal
  function closeUrlCopyModal() {
      $('#bf-url-copy-modal').fadeOut(300);
  }

  // Update URL display
  function updateUrlDisplay() {
    var filePath = $('#bf-url-copy-modal').data('file-path');
    var urlType = $('input[name="url_type"]:checked').val();
    var home = (typeof bfFileListData !== 'undefined' && bfFileListData.homeUrl) ? bfFileListData.homeUrl : (window.location.origin + '/');
    // ensure trailing slash just once
    if (home.slice(-1) !== '/') { home += '/'; }
    var url = home + '?path=' + encodeURIComponent(filePath) + '&dflag=' + urlType;
    $('#bf-url-input').val(url);

    // Update the preview frame (only for image files)
    updatePreviewFrame(url);
}

// Update the preview frame
function updatePreviewFrame(url) {
    var fileName = $('#bf-url-file-name').text();
    var urlType = $('input[name="url_type"]:checked').val();
    var previewFrame = $('#bf-url-preview-frame');

    // Display preview only for image files
    var imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'];
    var fileExtension = fileName.split('.').pop().toLowerCase();

    if (urlType === 'display' && imageExtensions.includes(fileExtension)) {
        previewFrame.attr('src', url);
        $('.bf-url-preview').show();
    } else {
        previewFrame.attr('src', '');
        $('.bf-url-preview').hide();
    }
  }


    // Copy URL to clipboard
    function copyUrlToClipboard() {
      var url = $('#bf-url-input').val();
      var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};

      // Use the modern browser Clipboard API
      if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(url).then(function() {
              showSuccessMessage((strings.downloadUrlCopied || 'Download URL copied to clipboard:') + ' ' + url);
          }).catch(function(err) {
              console.error(strings.failedToCopyClipboard || 'Failed to copy to clipboard:', err);
              copyUrlFallback(url);
          });
      } else {
          // Use a fallback (for older browsers)
          copyUrlFallback(url);
      }
  }

  // Open URL in a new tab
  function openUrlInNewTab() {
      var url = $('#bf-url-input').val();
      window.open(url, '_blank');
  }

  // URL copy fallback (for older browsers)
  function copyUrlFallback(url) {
      var textArea = document.createElement('textarea');
      textArea.value = url;
      textArea.style.position = 'fixed';
      textArea.style.top = '0';
      textArea.style.left = '0';
      textArea.style.width = '2em';
      textArea.style.height = '2em';
      textArea.style.padding = '0';
      textArea.style.border = 'none';
      textArea.style.outline = 'none';
      textArea.style.boxShadow = 'none';
      textArea.style.background = 'transparent';
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();

      try {
          var successful = document.execCommand('copy');
          if (successful) {
              var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
              showSuccessMessage((strings.downloadUrlCopied || 'Download URL copied to clipboard:') + ' ' + url);
          } else {
              showUrlPrompt(url);
          }
      } catch (err) {
          var strings2 = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
          console.error(strings2.failedToCopyClipboard || 'Failed to copy to clipboard:', err);
          showUrlPrompt(url);
      }

      document.body.removeChild(textArea);
  }

  // Display URL for manual copy
  function showUrlPrompt(url) {
      var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
      prompt(strings.pleaseCopyDownloadUrl || 'Please copy the following download URL:', url);
  }

    // Remove the directory authentication settings
    function removeDirectoryAuth() {
      var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
      if (!confirm(strings.removeAuthConfirm || 'Remove authentication settings for this directory?')) {
          return;
      }

      var currentPath = $('#current-path').val();

      // Disable button
      $('#bf-remove-auth').prop('disabled', true).text(strings.deleting || 'Deleting...');

      $.ajax({
          url: ajaxurl,
          type: 'POST',
          data: {
              action: 'bf_sfd_set_directory_auth',
              path: currentPath,
              action_type: 'remove',
              nonce: (typeof bfFileListData !== 'undefined' && bfFileListData.nonce) ? bfFileListData.nonce : ''
          },
          success: function(response) {
              if (response.success) {
                  showSuccessMessage(response.data.message);
                  closeDirectoryAuthModal();
                  updateAuthIndicator(response.data.has_auth);
              } else {
                  alert(response.data || (strings.failedToDeleteAuth || 'Failed to delete authentication settings.'));
              }
          },
          error: function() {
              alert(strings.communicationErrorOccurred || 'Communication error occurred.');
          },
          complete: function() {
              $('#bf-remove-auth').prop('disabled', false).text(strings.deleteAuthSettings || 'Delete authentication settings');
          }
      });
  }

  // Save the directory authentication settings
  function saveDirectoryAuth() {
    var currentPath = $('#current-path').val();
    var authMethods = [];
    var allowedRoles = [];
    var simpleAuthPassword = $('#bf-simple-auth-password').val().trim();

    // Get authentication methods
    $('input[name="bf_auth_methods[]"]:checked').each(function() {
        authMethods.push($(this).val());
    });

    // Get allowed roles
    $('input[name="bf_allowed_roles[]"]:checked').each(function() {
        allowedRoles.push($(this).val());
    });

    if (authMethods.length === 0) {
        var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
        alert(strings.pleaseSelectAuthMethod || 'Please select an authentication method.');
        return;
    }

    // If simple authentication is selected, a password is required
    if (authMethods.includes('simple_auth') && !simpleAuthPassword) {
        var strings2 = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
        alert(strings2.simpleAuthPasswordRequired || 'If you select simple authentication, please set a password.');
        $('#bf-simple-auth-password').focus();
        return;
    }

    // Disable button
    $('#bf-save-auth').prop('disabled', true).text((strings && strings.saving) || 'Saving...');

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'bf_sfd_set_directory_auth',
            path: currentPath,
            auth_methods: authMethods,
            allowed_roles: allowedRoles,
            simple_auth_password: simpleAuthPassword,
            action_type: 'set',
            nonce: (typeof bfFileListData !== 'undefined' && bfFileListData.nonce) ? bfFileListData.nonce : ''
        },
        success: function(response) {
            if (response.success) {
                showSuccessMessage(response.data.message);
                closeDirectoryAuthModal();
                updateAuthIndicator(response.data.has_auth);

                // Display authentication details
                if (response.data.has_auth) {
                    loadDirectoryAuthSettings(currentPath);
                }
            } else {
                alert(response.data || ((strings && strings.failedToSaveAuth) || 'Failed to save authentication settings.'));
            }
        },
        error: function() {
            alert((strings && strings.communicationErrorOccurred) || 'Communication error occurred.');
        },
        complete: function() {
            $('#bf-save-auth').prop('disabled', false).text((strings && strings.save) || 'Save');
        }
    });
  }

  $(function(){

    // Check if Dashicons are loaded
    checkDashicons();

    // Initialize authentication details display on page load
    setTimeout(function() {
        initializeAuthDetails();
    }, 200);

    // Delete link event (from mouse over menu)
    $(document).on('click', '.delete-file-link', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Stop event propagation
        var $link = $(this);
        var filePath = $link.data('file-path');
        var fileName = $link.data('file-name');
        var fileType = $link.data('file-type');

        deleteFile(filePath, fileName, fileType);
    });

    // Remove directory click processing - only row action links are used

    // Directory authentication settings button click processing
    $('#directory-auth-btn').on('click', function(e) {
        e.preventDefault();
        openDirectoryAuthModal();
    });

    // Authentication settings modal related events
    $('.bf-modal-close, #bf-cancel-auth').on('click', function() {
        closeDirectoryAuthModal();
    });

    // Close authentication settings modal by clicking outside
    $('#bf-directory-auth-modal').on('click', function(e) {
        if (e.target === this) {
            closeDirectoryAuthModal();
        }
    });

    // Simple authentication checkbox control
    $(document).on('change', '#bf-auth-methods-simple-auth', function() {
        if ($(this).is(':checked')) {
            $('#bf-simple-auth-password-section').show();
        } else {
            $('#bf-simple-auth-password-section').hide();
        }
    });

    // Authentication settings save button
    $('#bf-save-auth').on('click', function() {
        saveDirectoryAuth();
    });

    // Authentication settings delete button
    $('#bf-remove-auth').on('click', function() {
        removeDirectoryAuth();
    });

    // URL copy modal related events
    $('.bf-modal-close, #bf-close-url-modal').on('click', function() {
        closeUrlCopyModal();
    });

    // Close URL copy modal by clicking outside
    $('#bf-url-copy-modal').on('click', function(e) {
        if (e.target === this) {
            closeUrlCopyModal();
        }
    });

    // Password display/hide toggle
    $('#bf-password-toggle').on('click', function() {
        var passwordField = $('#bf-directory-password-input');
        var button = $(this);
        var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};

        if (passwordField.attr('type') === 'password') {
            passwordField.attr('type', 'text');
            button.text(strings.hide || 'Hide');
        } else {
            passwordField.attr('type', 'password');
            button.text(strings.show || 'Show');
        }
    });

    // Current password display button
    $('#bf-show-current-password').on('click', function() {
        showCurrentPassword();
    });

    // URL copy modal related events
    $(document).on('change', 'input[name="url_type"]', function() {
        updateUrlDisplay();
    });

    // URL copy button
    $('#bf-copy-url-btn').on('click', function() {
        copyUrlToClipboard();
    });

    // Open in new tab button
    $('#bf-open-url-btn').on('click', function() {
        openUrlInNewTab();
    });

    // Go up button click processing
    $('#go-up-btn').on('click', function(e) {
        e.preventDefault();
        var currentPath = $('#current-path').val();
        if (currentPath) {
            var parentPath = getParentPath(currentPath);
            navigateToDirectory(parentPath, 1);
        }
    });

    // Sort link click processing
    $(document).on('click', '.sort-link', function(e) {
        e.preventDefault();
        var sortBy = $(this).data('sort');
        var currentPath = $('#current-path').val();
        var currentSortBy = getCurrentSortBy();
        var currentSortOrder = getCurrentSortOrder();

        // If the same column is clicked, reverse the order
        var newSortOrder = 'asc';
        if (sortBy === currentSortBy && currentSortOrder === 'asc') {
            newSortOrder = 'desc';
        }

        navigateToDirectoryWithSort(currentPath, 1, sortBy, newSortOrder);
    });

    // Paging link click processing
    $(document).on('click', '.pagination-links a', function(e) {
        e.preventDefault();
        var url = new URL(this.href);
        var page = url.searchParams.get('paged') || 1;
        var path = url.searchParams.get('path') || $('#current-path').val();
        navigateToDirectory(path, page);
    });

    // Directory creation button click processing
    $('#create-directory-btn').on('click', function(e) {
        e.preventDefault();
        $('#create-directory-form').slideDown();
        $('#directory-name-input').focus();
    });

    // Directory creation form cancel
    $('#create-directory-cancel').on('click', function(e) {
        e.preventDefault();
        $('#create-directory-form').slideUp();
        $('#directory-name-input').val('');
    });

    // Execute directory creation
    $('#create-directory-submit').on('click', function(e) {
        e.preventDefault();
        createDirectory();
    });

    // Enter key to create directory
    $('#directory-name-input').on('keypress', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            createDirectory();
        }
    });

    // Download link event
    $(document).on('click', '.download-file-link', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Stop event propagation
        var $link = $(this);
        var filePath = $link.data('file-path');
        var fileName = $link.data('file-name');

        downloadFile(filePath, fileName);
    });

    // URL copy link event
    $(document).on('click', '.copy-url-link', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Stop event propagation
        var $link = $(this);
        var filePath = $link.data('file-path');
        var fileName = $link.data('file-name');

        openUrlCopyModal(filePath, fileName);
    });

    // Open directory link event
    $(document).on('click', '.open-directory', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Stop event propagation
        var $link = $(this);
        var path = $link.data('path');

        if (path) {
            navigateToDirectory(path, 1);
        }
    });

    // All selection checkbox event
    $(document).on('change', '#cb-select-all-1', function() {
        var isChecked = $(this).prop('checked');
        $('input[name="file_paths[]"]').prop('checked', isChecked);
    });

    // Individual checkbox event
    $(document).on('change', 'input[name="file_paths[]"]', function() {
        var totalCheckboxes = $('input[name="file_paths[]"]').length;
        var checkedCheckboxes = $('input[name="file_paths[]"]:checked').length;

        // If all checkboxes are checked, check the all selection checkbox
        $('#cb-select-all-1').prop('checked', totalCheckboxes === checkedCheckboxes);
    });

    // Stop event propagation when clicking checkbox
    $(document).on('click', 'input[name="file_paths[]"]', function(e) {
        e.stopPropagation();
    });

    // Stop event propagation when clicking checkbox label
    $(document).on('click', '.check-column label', function(e) {
        e.stopPropagation();
    });

    // Bulk operation button event
    $(document).on('click', '#doaction', function(e) {
        e.preventDefault();

        var action = $('#bulk-action-selector-top').val();
        if (action === '-1') {
            var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
            alert(strings.pleaseSelectAction || 'Please select an action.');
            return;
        }

        var checkedFiles = $('input[name="file_paths[]"]:checked');
        if (checkedFiles.length === 0) {
            var strings2 = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
            alert(strings2.pleaseSelectItemsToDelete || 'Please select items to delete.');
            return;
        }

        if (action === 'delete') {
            bulkDeleteFiles();
        }
    });

    // File selection button click processing
    $('#select-files-btn').on('click', function(e) {
        e.preventDefault();
        $('#file-input').click();
    });

    // File selection processing
    $('#file-input').on('change', function(e) {
        var files = e.target.files;
        if (files.length > 0) {
            uploadFiles(files);
        }
    });

    // Drag and drop processing
    var dropZone = $('#drop-zone');

    if (dropZone.length > 0) {
        // Drag enter
        dropZone.on('dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
            $('.drop-zone-overlay').show();
        });

        // Drag over
        dropZone.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });

        // Drag leave
        dropZone.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var rect = this.getBoundingClientRect();
            var x = e.originalEvent.clientX;
            var y = e.originalEvent.clientY;

            // Only process if it is outside the drop zone
            if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                $(this).removeClass('dragover');
                $('.drop-zone-overlay').hide();
            }
        });

        // Drop
        dropZone.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
            $('.drop-zone-overlay').hide();

            var files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                uploadFiles(files);
            }
        });

        // Disable default drag and drop on the entire page
        $(document).on('dragenter dragover drop', function(e) {
            e.preventDefault();
        });
    }

    // Control simple authentication checkbox
    $('#bf-auth-methods-simple-auth').on('change', function() {
        if ($(this).is(':checked')) {
            $('#bf-simple-auth-password-section').show();
        } else {
            $('#bf-simple-auth-password-section').hide();
        }
    });

    // Control login user checkbox
    $('#bf-auth-methods-logged-in').on('change', function() {
        if ($(this).is(':checked')) {
            $('#bf-allowed-roles-section').show();
        } else {
            $('#bf-allowed-roles-section').hide();
        }
    });

    // Control role selection
    $('#bf-select-all-roles').on('click', function() {
        $('.bf-role-checkbox').prop('checked', true);
    });

    $('#bf-deselect-all-roles').on('click', function() {
        $('.bf-role-checkbox').prop('checked', false);
    });

    // Remove authentication settings button event listener
    $(document).on('click', '#remove-auth-btn', function() {
        removeDirectoryAuth();
    });

    // Secure directory re-creation button processing
    $('#bf-recreate-secure-directory').on('click', function() {
        var $button = $(this);
        var $status = $('#bf-recreate-status');

        // Disable button
        var strings3 = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
        $button.prop('disabled', true).text(strings3.creating || 'Creating...');
        $status.html('<span style="color: #0073aa;">' + (strings3.processing || 'Processing...') + '</span>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bf_sfd_recreate_secure_directory',
                nonce: (typeof bfFileListData !== 'undefined' && bfFileListData.nonce) ? bfFileListData.nonce : ''
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: #46b450;">' + response.data.message + '</span>');

                    // Reload the page after 3 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    var strings4 = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
                    $status.html('<span style="color: #dc3232;">' + response.data + '</span>');
                    $button.prop('disabled', false).text(strings4.createDirectory || 'Create directory');
                }
            },
            error: function(xhr, status, error) {
                var strings5 = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
                $status.html('<span style="color: #dc3232;">' + (strings5.anErrorOccurred || 'An error occurred') + ': ' + error + '</span>');
                $button.prop('disabled', false).text(strings5.createDirectory || 'Create directory');
            }
        });
    });

    // Display initial data (using data passed from wp_localize_script)
    if (typeof bfFileListData !== 'undefined' && bfFileListData.initialData) {
        updateFileList(bfFileListData.initialData);
    }
  });
})(jQuery);
