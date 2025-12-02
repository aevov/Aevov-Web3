(function($) {
    'use strict';

    $(document).ready(function() {
        var $downloadSelectedButton = $('#acd-download-selected');
        var $patternCheckboxes = $('.acd-pattern-checkbox');
        var $progressContainer = $('#acd-progress-container');
        var $progressBar = $('#acd-progress-bar');
        var $progressText = $('#acd-progress-text');
        var $cancelButton = $('#acd-cancel-download');
        var currentDownloadId = null;

        function updateDownloadButtonState() {
            var selectedPatterns = $patternCheckboxes.filter(':checked');
            if (selectedPatterns.length > 0) {
                $downloadSelectedButton.prop('disabled', false);
            } else {
                $downloadSelectedButton.prop('disabled', true);
            }
        }

        $patternCheckboxes.on('change', function() {
            updateDownloadButtonState();
        });

        $downloadSelectedButton.on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var selectedPatterns = $patternCheckboxes.filter(':checked').map(function() {
                return $(this).val();
            }).get();

            if (selectedPatterns.length === 0) {
                alert('Please select at least one pattern to download.');
                return;
            }

            $button.text('Downloading...');
            $progressContainer.show();
            $progressBar.css('width', '0%');
            $progressText.text('');

            $.ajax({
                url: acdAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'acd_download_pattern',
                    nonce: acdAdmin.nonce,
                    pattern_ids: selectedPatterns
                },
                success: function(response) {
                    if (response.success) {
                        currentDownloadId = response.data.download_id;
                        pollDownloadProgress(currentDownloadId);
                    } else {
                        $button.text('Download Failed');
                        $progressContainer.hide();
                        alert(response.data.message);
                    }
                },
                error: function() {
                    $button.text('Download Failed');
                    $progressContainer.hide();
                    alert('An error occurred while trying to download the patterns.');
                }
            });
        });

        $cancelButton.on('click', function(e) {
            e.preventDefault();

            if (currentDownloadId) {
                $.ajax({
                    url: acdAdmin.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'acd_cancel_download',
                        nonce: acdAdmin.nonce,
                        download_id: currentDownloadId
                    },
                    success: function() {
                        $downloadSelectedButton.text('Download Selected');
                        $progressContainer.hide();
                        currentDownloadId = null;
                    }
                });
            }
        });

        function pollDownloadProgress(downloadId) {
            if (downloadId !== currentDownloadId) {
                return; // A new download has started, so stop polling for the old one.
            }

            $.ajax({
                url: cubbitAuthData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'cubbit_check_zip_status',
                    nonce: cubbitAuthData.nonce,
                    download_id: downloadId
                },
                success: function(response) {
                    if (response.success) {
                        var status = response.data.status;
                        var progress = response.data.progress;
                        var progressText = response.data.progress_text;

                        $progressBar.css('width', progress + '%');
                        $progressText.text(progressText);

                        if (status === 'completed') {
                            $downloadSelectedButton.text('Download Selected');
                            $progressContainer.hide();
                            currentDownloadId = null;
                            window.location.href = response.data.download_url;
                        } else if (status === 'failed') {
                            $downloadSelectedButton.text('Download Failed');
                            $progressContainer.hide();
                            currentDownloadId = null;
                            alert('Download failed: ' + response.data.errors.join(', '));
                        } else {
                            setTimeout(function() {
                                pollDownloadProgress(downloadId);
                            }, 2000);
                        }
                    } else {
                        $downloadSelectedButton.text('Download Failed');
                        $progressContainer.hide();
                        currentDownloadId = null;
                        alert('An error occurred while checking the download status.');
                    }
                },
                error: function() {
                    $downloadSelectedButton.text('Download Failed');
                    $progressContainer.hide();
                    currentDownloadId = null;
                    alert('An error occurred while checking the download status.');
                }
            });
        }

        updateDownloadButtonState();
    });
})(jQuery);
