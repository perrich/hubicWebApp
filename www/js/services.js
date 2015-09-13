(function () {
    'use strict';

	angular.module("filestorage.services", ['app.config', 'ngFileUpload']);

	angular.module("filestorage.services").service('hubicService', HubicService);
	HubicService.$inject = ['$q', '$http', 'Upload', 'appConfig'];

	function HubicService($q, $http, Upload, appConfig) {
		var baseUri = 'router.php';
		var baseFileUri = baseUri + '/file';
		var baseFolderUri = baseUri + '/folder';

		this.setEncryptionKey = setEncryptionKey;

		this.getFolder = getFolder;
		this.createFolder = createFolder;
		this.deleteFolder = deleteFolder;

		this.getFilename = getFilename;

		this.addFile = addFile;
		this.getFile = getFile;
		this.deleteFile = deleteFile;


		function encodeFolderName(folder) {
			return (angular.isDefined(folder) ? folder.replace(/\//g, '|') : '');
		}

		function setEncryptionKey(key) {
			var deferred = $q.defer();

			$http.post(baseUri + '/crypt/define', {
				key: key
			}).success(function (data) {
				deferred.resolve(data);
			}).error(function () {
				deferred.reject("not created");
			});

			return deferred.promise;
		}

		function getFolder(folder) {
			var deferred = $q.defer();

			$http.get(baseFolderUri + '/' + encodeFolderName(folder)).success(function (data) {
				angular.forEach(data, function (file) {
					file.encrypted = file.name.endsWith(appConfig.encrypted_file_ext);
				});
				deferred.resolve(data);
			}).error(function () {
				deferred.reject("not found");
			});

			return deferred.promise;
		};

		function createFolder(folder) {
			var deferred = $q.defer();

			$http.post(baseFolderUri + '/' + encodeFolderName(folder)).success(function (data) {
				deferred.resolve(data);
			}).error(function () {
				deferred.reject("not created");
			});

			return deferred.promise;
		};

		function deleteFolder(folder) {
			var deferred = $q.defer();

			$http.delete(baseFolderUri + '/' + encodeFolderName(folder)).success(function (data) {
				deferred.resolve(data);
			}).error(function () {
				deferred.reject("not deleted");
			});

			return deferred.promise;
		};

		function getFilename(filename, encrypt) {
			return filename + (encrypt === true ? appConfig.encrypted_file_ext : '');
		}

		function addFile(filename, file, encrypt) {
			var deferred = $q.defer();
			var path = encodeFolderName(this.getFilename(filename, encrypt));

			Upload.upload({
				url: baseFileUri + '/' + path,
				file: file
			}).progress(function (evt) {
				var percentComplete = Math.round(100.0 * evt.loaded / evt.total, 0);
				deferred.notify(percentComplete);
			}).success(function (data, status, headers, config) {
				if (data.result !== undefined && data.result === 'done') {
					deferred.resolve(data);
				}
				deferred.reject("not created");
			}).error(function (data, status, headers, config) {
				deferred.reject("not created");
			});

			return deferred.promise;
		};

		function getFile(path) {
			var deferred = $q.defer();

			$http.get(baseFileUri + '/' + encodeFolderName(path), {
				responseType: 'arraybuffer',
				cache: false
			}).success(function (data) {
				deferred.resolve(new Blob([data], {
					type: 'application/octet_stream'
				}));
			}).error(function () {
				deferred.reject("not deleted");
			});

			return deferred.promise;
		};

		function deleteFile(path) {
			var deferred = $q.defer();

			$http.delete(baseFileUri + '/' + encodeFolderName(path)).success(function (data) {
				deferred.resolve(data);
			}).error(function () {
				deferred.reject("not deleted");
			});

			return deferred.promise;
		};
	}
})();