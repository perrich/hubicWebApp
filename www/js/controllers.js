(function () {
    'use strict';

	angular.module("filestorage.controllers", ["app.config", "filestorage.services"]);

	angular.module("filestorage.controllers").controller("KeyController", KeyController);
	KeyController.$inject = ["$location", "hubicService", "toaster"];

	function KeyController($location, hubicService, toaster) {
		var vm = this;

		this.defineKey = function () {
			hubicService.setEncryptionKey(vm.key).then(function () {
				$location.path("/folder");
			}, function (reason) {
				toaster.pop("error", "", "key cannot be set.", 5000);
			});
		}
	}

	angular.module("filestorage.controllers").controller("FolderController", FolderController);
	FolderController.$inject = ["$routeParams", "$scope", "$location", "$timeout", "$q", "hubicService", "appConfig", "toaster"];

	function FolderController($routeParams, $scope, $location, $timeout, $q, hubicService, appConfig, toaster) {
		var vm = this;

		vm.processing = false;
		vm.loaded = false;
		vm.files = [];
		vm.newFile = null;

		vm.getType = getType;
		vm.getName = getName;
		
		vm.deleteFolder = deleteFolder;
		vm.createFolder = createFolder;
		
		vm.openPath = openPath;
		vm.chooseFile = chooseFile;
		vm.addFile = addFile;

		function encodePath(path) {
			return encodeURIComponent(path.replace(/\//g, "|"))
		}

		function decodePath(path) {
			return decodeURIComponent(path).replace(/\|/g, "/")
		}

		function getFullPath(path) {
			return (angular.isDefined(vm.folder) ? vm.folder : "") + (angular.isDefined(path) ? "/" + path : "");
		}

		function getFolder() {
			vm.loaded = false;
			hubicService.getFolder(vm.folder).then(function (files) {
				vm.files = files;
			}).finally(function () {
				vm.loaded = true;
			});
		};
		
		function openFolder(path) {
			$location.path("/folder/" + encodePath(path));
		}

		this.menuOptions = [
			["Delete", function ($itemScope) {
				deletePath($itemScope.file).then(function () {
					getFolder();
					toaster.pop("success", "", $itemScope.file.name + " is deleted.", 5000);
				}, function (reason) {
					toaster.pop("error", "", $itemScope.file.name + " cannot be deleted.", 5000);
				});
			}, function ($itemScope) {
				return vm.loaded;
			}],
			null,
			["Rename", function ($itemScope) {
				renamePath($itemScope.file).then(function () {
					getFolder();
					toaster.pop("success", "", $itemScope.file.name + " is renamed.", 5000);
				}, function (reason) {
					toaster.pop("error", "", $itemScope.file.name + " cannot be renamed.", 5000);
				});
			}, function ($itemScope) {
				return vm.loaded && false; // not implemented, so desactivate it
			}]
		];

		function getType(file) {
			if (file.type == "folder") {
				return file.empty ? "fa fa-folder" : "fa fa-folder-open";
			} else {
				return "fa fa-file-o";
			}
		}

		function getName(file) {
			if (file.encrypted === true) {
				return file.name.substring(0, file.name.length - appConfig.encrypted_file_ext.length);
			} else {
				return file.name;
			}
		}

		function openPath(file) {
			var path = getFullPath(file.name);

			if (file.type == "folder") {
				openFolder(path);
			} else if (file.type == "file") {
				$("#pleaseWaitDialog").modal("show");
				hubicService.getFile(path).then(function (blob) {
					window.saveAs(blob, vm.getName(file));
				}, function (reason) {
					toaster.pop("error", "", file.name + " cannot be opened.", 5000);
				}).finally(function () {
					$("#pleaseWaitDialog").modal("hide");
				});
			}
		}

		function deletePath(file) {
			var deferred = $q.defer();
			$("#pleaseWaitDialog").modal("show");

			if (file.type == "folder") {
				var path = getFullPath(file.name);
				hubicService.deleteFolder(path).then(function (data) {
					deferred.resolve(data);
				}, function (data) {
					deferred.reject("not done");
				}).finally(function () {
					$("#pleaseWaitDialog").modal("hide");
				});
			} else if (file.type == "file") {
				hubicService.deleteFile(file.name).then(function (data) {
					deferred.resolve(data);
				}, function (data) {
					deferred.reject("not done");
				}).finally(function () {
					$("#pleaseWaitDialog").modal("hide");
				});
			}

			return deferred.promise;
		}

		function renamePath(file) {
			var deferred = $q.defer();

			if (file.type == "folder") {
				hubicService.renameFolder(file.name).then(function (data) {
					deferred.resolve(data);
				}, function (data) {
					deferred.reject("not done");
				});
			} else if (file.type == "file") {
				hubicService.renameFile(file.name).then(function (data) {
					deferred.resolve(data);
				}, function (data) {
					deferred.reject("not done");
				});
			}

			return deferred.promise;
		}

		function deleteFolder() {
			var path = vm.folder.substring(0, vm.folder.lastIndexOf("/"));
			vm.processing = true;
			deletePath({
				name: undefined, // need to delete current folder
				type: "folder"
			}).then(function () {
				$("#deleteForm").modal("hide");
				toaster.pop("success", "", vm.folder + " folder is deleted.", 5000);
				$timeout(function () {
					$location.path("/folder/" + encodePath(path));
				}, 2000);
			}, function (reason) {
				toaster.pop("error", "", vm.folder + " cannot be deleted.", 5000);
			}).finally(function () {
				vm.processing = false;
			});
		}

		function createFolder() {
			var path = getFullPath(vm.newName);

			vm.processing = true;
			hubicService.createFolder(path).then(function () {
				vm.files.unshift({
					name: vm.newName,
					type: "folder",
					empty: true
				});
				$("#createForm").modal("hide");
				toaster.pop("success", "", vm.newName + " folder is created.", 5000);
			}, function (reason) {
				toaster.pop("error", "", vm.newName + " folder cannot be created.", 5000);
			}).finally(function () {
				vm.processing = false;
			});
		}

		function chooseFile() {
			$("#encrypt-input").click();
		}

		function addFile() {
			var path = getFullPath(vm.newFilename);
			var progress = $("#sendProgress");
			progress.css("width", 0 + "%");
			progress.text(0 + "% sent");
			progress.parent().removeClass("hidden");
			vm.processing = true;

			hubicService.addFile(path, vm.newFile, vm.encrypt).then(function () {
				vm.files.unshift({
					name: hubicService.getFilename(vm.newFilename, vm.encrypt),
					type: "file",
					encrypted: vm.encrypt === true,
					empty: true
				});
				$("#addForm").modal("hide");
				toaster.pop("success", "", vm.newFile + " file is created.", 5000);
			}, function (reason) {
				toaster.pop("error", "", vm.newFile + " file cannot be created.", 5000);
			}, function (update) {
				progress.css("width", (update) + "%");
				progress.text((update) + "% sent");
				
				// set infinite loop until server upload the request to hubic
				if (update >= 100) {
					progress.parent().addClass("active");
					progress.text("waiting reponse...");
				}
			}).finally(function () {
				vm.processing = false;
			});
		}

		function init() {
			vm.folder = decodePath(angular.isDefined($routeParams.folder) ? $routeParams.folder : "");
			getFolder();

			var pathList = [{
				label: "",
				path: "/folder/",
			}];
			var currentPath = "";
			var pos = 1;
			angular.forEach(vm.folder.split("/"), function (el) {
				if (el != "") {
					currentPath = currentPath + "/" + el;
					pathList.push({
						pos: pos,
						label: el,
						path: "/folder/" + encodePath(currentPath),
					});
					pos++;
				}
			});

			vm.pathList = pathList;

			$("#addForm").on("change", "#encrypt-input", function (e) {
				$scope.$apply(function () {
					vm.newFile = e.target.files[0];
					vm.newFilename = vm.newFile.name;
				});
			});
	
			$("#addForm").on("hidden.bs.modal", function (e) {
				var progress = $("#sendProgress");
				if (!progress.parent().hasClass("hidden")) {
					progress.parent().addClass("hidden");
				}
	
				vm.newFilename = null;
				vm.encrypt = false;
			});
	
			$("#createForm").on("shown.bs.modal", function (e) {
				$("#new-name").focus();
			});
		}
		init();
	}
})();