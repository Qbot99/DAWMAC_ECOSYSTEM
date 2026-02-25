//
//  ContentView.swift
//  DawMac
//
//  Created by Hubert Kubot on 27/01/2026.
//

import SwiftUI
import PhotosUI

// ==========================================
// 1. HELPER DO ZAPISYWANIA ZDJĘĆ
// ==========================================

class ImageSaver: NSObject {
    static let shared = ImageSaver()
    func saveImage(_ image: UIImage) {
        UIImageWriteToSavedPhotosAlbum(image, nil, nil, nil)
    }
}

// ==========================================
// 2. KOMPONENTY UI
// ==========================================

struct BigButtonStyle: ButtonStyle {
    var color: Color
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.headline).bold().foregroundColor(.white).padding()
            .frame(maxWidth: .infinity).background(color).cornerRadius(12)
            .scaleEffect(configuration.isPressed ? 0.95 : 1.0)
            .shadow(color: color.opacity(0.3), radius: 5, x: 0, y: 3)
    }
}

struct ImageCell: View {
    let image: UIImage
    let onDelete: () -> Void
    var body: some View {
        ZStack(alignment: .topTrailing) {
            Image(uiImage: image).resizable().scaledToFill().frame(width: 100, height: 100).cornerRadius(8).clipped()
            Button(action: onDelete) {
                Image(systemName: "xmark.circle.fill").foregroundStyle(.white, .red).font(.system(size: 22))
            }.offset(x: 5, y: -5)
        }
    }
}

struct RowImageCell: View {
    let url: String
    let onDelete: () -> Void
    @State private var didSave = false
    
    var body: some View {
        HStack {
            SharedImageView(urlString: url, width: 80, height: 60, allowSave: false)
                .cornerRadius(6)
            Spacer()
            HStack(spacing: 20) {
                Button(action: saveSingleImage) {
                    Image(systemName: didSave ? "checkmark.circle.fill" : "arrow.down.circle.fill")
                        .font(.system(size: 24))
                        .foregroundColor(didSave ? .green : .blue)
                }
                .buttonStyle(PlainButtonStyle())
                
                Button(action: onDelete) {
                    Image(systemName: "trash.circle.fill")
                        .font(.system(size: 24))
                        .foregroundColor(.red)
                }
                .buttonStyle(PlainButtonStyle())
            }
        }
        .padding(.vertical, 4)
    }
    
    func saveSingleImage() {
        guard let validUrl = APIService.shared.safeURL(string: url) else { return }
        APIService.shared.fetchImage(url: validUrl) { image in
            if let img = image {
                ImageSaver.shared.saveImage(img)
                withAnimation { didSave = true }
                DispatchQueue.main.asyncAfter(deadline: .now() + 1.5) {
                    withAnimation { didSave = false }
                }
            }
        }
    }
}

struct SharedImageView: View {
    let urlString: String?
    var width: CGFloat?
    var height: CGFloat
    var allowSave: Bool = true
    
    @State private var image: UIImage? = nil
    @State private var isLoading = false
    
    var body: some View {
        ZStack {
            if let image = image {
                Image(uiImage: image).resizable().scaledToFill().frame(width: width, height: height).clipped()
                    .contextMenu(allowSave ? ContextMenu {
                        Button {
                            let clean = image.fixOrientationAndResize()
                            ImageSaver.shared.saveImage(clean)
                        } label: { Label("Zapisz to zdjęcie", systemImage: "square.and.arrow.down") }
                    } : nil)
            } else {
                Rectangle().fill(Color.gray.opacity(0.1)).frame(width: width, height: height)
                    .overlay(Group { if isLoading { ProgressView() } else { Image(systemName: "photo").foregroundColor(.gray) } })
            }
        }
        .cornerRadius(8)
        .onAppear { loadImage() }
        .onChange(of: urlString) { _, _ in loadImage() }
    }
    
    private func loadImage() {
        guard let urlString = urlString, let url = APIService.shared.safeURL(string: urlString) else { return }
        if image != nil { return }
        isLoading = true
        APIService.shared.fetchImage(url: url) { loaded in
            self.isLoading = false
            if let img = loaded { self.image = img }
        }
    }
}

// ==========================================
// 3. GŁÓWNY WIDOK
// ==========================================

struct ContentView: View {
    @StateObject var galleryVM = GalleryViewModel()
    @StateObject var forgedVM = ForgedViewModel()
    
    var body: some View {
        TabView {
            GalleryListView(viewModel: galleryVM).tabItem { Label("Auta", systemImage: "car.2.fill") }
            ForgedListView(viewModel: forgedVM).tabItem { Label("Felgi", systemImage: "circle.grid.cross.fill") }
        }
    }
}

// ==========================================
// LISTY
// ==========================================

struct GalleryListView: View {
    @ObservedObject var viewModel: GalleryViewModel
    @State private var showAddSheet = false
    var body: some View {
        NavigationView {
            ScrollView {
                VStack(spacing: 10) {
                    HStack {
                        Picker("Marka", selection: $viewModel.filterBrand) {
                            Text("Wszystkie marki").tag(nil as Brand?)
                            ForEach(viewModel.brands) { b in
                                Text(b.name).tag(b as Brand?)
                            }
                        }
                        .pickerStyle(.menu).padding(8).background(Color(UIColor.secondarySystemGroupedBackground)).cornerRadius(8)
                        
                        if viewModel.filterBrand != nil {
                            Picker("Model", selection: $viewModel.filterModel) {
                                Text("Wszystkie modele").tag(nil as CarModel?)
                                ForEach(viewModel.availableFilterModels) { m in
                                    Text(m.name).tag(m as CarModel?)
                                }
                            }
                            .pickerStyle(.menu).padding(8).background(Color(UIColor.secondarySystemGroupedBackground)).cornerRadius(8)
                        }
                        Spacer()
                        if viewModel.filterBrand != nil || viewModel.filterModel != nil {
                            Button(action: { withAnimation { viewModel.filterBrand = nil; viewModel.filterModel = nil } }) {
                                Image(systemName: "xmark.circle.fill").font(.system(size: 22)).foregroundColor(.gray)
                            }.padding(.trailing, 8)
                        }
                    }.padding(.horizontal).padding(.top, 5)
                }
                
                if viewModel.isLoading && viewModel.projects.isEmpty { ProgressView("Pobieram...").padding(.top, 50) }
                LazyVStack(spacing: 15) {
                    ForEach(viewModel.filteredProjects, id: \.id) { item in
                        NavigationLink(destination: GalleryEditView(item: item, viewModel: viewModel)) { ProjectRow(item: item) }
                    }
                }.padding()
            }
            .background(Color(UIColor.systemGroupedBackground))
            .navigationTitle("Lista Aut")
            .searchable(text: $viewModel.searchText)
            .toolbar { ToolbarItem(placement: .navigationBarTrailing) { Button(action: { showAddSheet = true }) { Image(systemName: "plus.circle.fill").font(.system(size: 24)) } } }
            .sheet(isPresented: $showAddSheet) { GalleryAddView(viewModel: viewModel) }
            .onAppear { if viewModel.brands.isEmpty { viewModel.fetchFormData() }; if viewModel.projects.isEmpty { viewModel.fetchProjects() } }
            .refreshable { viewModel.fetchProjects(); viewModel.fetchFormData() }
        }
    }
}

struct ForgedListView: View {
    @ObservedObject var viewModel: ForgedViewModel
    @State private var showAddSheet = false
    var body: some View {
        NavigationView {
            ScrollView {
                if viewModel.isLoading && viewModel.wheels.isEmpty { ProgressView("Pobieram...").padding(.top, 50) }
                LazyVStack(spacing: 15) {
                    ForEach(viewModel.filteredWheels, id: \.idString) { item in
                        NavigationLink(destination: ForgedEditView(item: item, viewModel: viewModel)) { ForgedRow(item: item) }
                    }
                }.padding()
            }
            .background(Color(UIColor.systemGroupedBackground))
            .navigationTitle("Lista Forged")
            .searchable(text: $viewModel.searchText)
            .toolbar { ToolbarItem(placement: .navigationBarTrailing) { Button(action: { showAddSheet = true }) { Image(systemName: "plus.circle.fill").font(.system(size: 24)) } } }
            .sheet(isPresented: $showAddSheet) { ForgedAddView(viewModel: viewModel) }
            .onAppear { if viewModel.wheels.isEmpty { viewModel.fetchWheels() } }
            .refreshable { viewModel.fetchWheels() }
        }
    }
}

// ==========================================
// WIERSZE
// ==========================================

struct ProjectRow: View {
    let item: ProjectItem
    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                VStack(alignment: .leading, spacing: 2) {
                    Text("\(item.brand_name ?? "---") \(item.model_name ?? "")").font(.headline).foregroundColor(.primary)
                    Text(item.wheel_brand ?? "").font(.subheadline).foregroundColor(.secondary)
                }
                Spacer(); Image(systemName: "chevron.right").foregroundColor(.gray)
            }
            .padding(.horizontal).padding(.top, 10)
            
            if !item.correctedGalleryImages.isEmpty {
                ScrollView(.horizontal, showsIndicators: false) {
                    HStack(spacing: 8) {
                        ForEach(item.correctedGalleryImages, id: \.self) { imgUrl in
                            SharedImageView(urlString: imgUrl, width: 100, height: 100)
                        }
                    }.padding(.horizontal)
                }
            } else {
                SharedImageView(urlString: item.correctedURL, width: nil, height: 150).padding(.horizontal)
            }
        }.padding(.bottom, 12).background(Color(UIColor.secondarySystemGroupedBackground)).cornerRadius(12).shadow(color: .black.opacity(0.05), radius: 2, x: 0, y: 2)
    }
}

struct ForgedRow: View {
    let item: WheelItem
    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack { Text(item.name).font(.headline).foregroundColor(.primary); Spacer(); Image(systemName: "chevron.right").foregroundColor(.gray) }.padding(.horizontal).padding(.top, 10)
            if !item.correctedGalleryImages.isEmpty {
                ScrollView(.horizontal, showsIndicators: false) {
                    HStack(spacing: 8) {
                        ForEach(item.correctedGalleryImages, id: \.self) { imgUrl in
                            SharedImageView(urlString: imgUrl, width: 100, height: 100)
                        }
                    }.padding(.horizontal)
                }
            } else {
                SharedImageView(urlString: item.correctedURL, width: nil, height: 150).padding(.horizontal)
            }
        }.padding(.bottom, 12).background(Color(UIColor.secondarySystemGroupedBackground)).cornerRadius(12).shadow(color: .black.opacity(0.05), radius: 2, x: 0, y: 2)
    }
}

// ==========================================
// DODAWANIE
// ==========================================

struct GalleryAddView: View {
    @ObservedObject var viewModel: GalleryViewModel
    @Environment(\.presentationMode) var presentationMode
    @State private var selectedBrand: Brand?; @State private var selectedModel: CarModel?
    @State private var wheelBrand = ""; @State private var wheelModel = ""; @State private var wheelParams = ""
    @State private var selectedItems: [PhotosPickerItem] = []; @State private var selectedImages: [UIImage] = []
    @State private var isCameraOpen = false; @State private var cameraImage: UIImage?
    @State private var isUploading = false; @State private var alertMsg = ""; @State private var showAlert = false

    var filteredModels: [CarModel] {
        guard let brand = selectedBrand else { return [] }
        return viewModel.allModels.filter { $0.car_brand_id?.value == brand.id.value }
    }

    var body: some View {
        NavigationView {
            Form {
                Section(header: Text("Auto")) {
                    Picker("Marka", selection: $selectedBrand) { Text("Wybierz...").tag(nil as Brand?); ForEach(viewModel.brands) { b in Text(b.name).tag(b as Brand?) } }
                    if selectedBrand != nil { Picker("Model", selection: $selectedModel) { Text("Wybierz...").tag(nil as CarModel?); ForEach(filteredModels) { m in Text(m.name).tag(m as CarModel?) } } }
                }
                Section(header: Text("Felgi")) {
                    TextField("Marka", text: $wheelBrand).textInputAutocapitalization(.words)
                    TextField("Model", text: $wheelModel).textInputAutocapitalization(.words)
                    TextField("Parametry", text: $wheelParams).textInputAutocapitalization(.sentences)
                }
                Section(header: Text("Zdjęcia (\(selectedImages.count))")) {
                    if !selectedImages.isEmpty {
                        ScrollView(.horizontal, showsIndicators: false) {
                            HStack {
                                ForEach(0..<selectedImages.count, id: \.self) { i in
                                    ImageCell(image: selectedImages[i], onDelete: { withAnimation { if selectedImages.indices.contains(i) { selectedImages.remove(at: i) } } })
                                }
                            }
                        }.frame(height: 120)
                    }
                    HStack(spacing: 15) {
                        PhotosPicker(selection: $selectedItems, matching: .images) { Label("Galeria", systemImage: "photo.on.rectangle") }.buttonStyle(BigButtonStyle(color: .blue))
                        Button(action: { isCameraOpen = true }) { Label("Aparat", systemImage: "camera.fill") }.buttonStyle(BigButtonStyle(color: .green))
                    }
                }
            }
            .navigationTitle("Dodaj Auto")
            .toolbar { ToolbarItem(placement: .confirmationAction) { Button("Zapisz") { uploadData() }.disabled(isUploading || selectedBrand == nil || selectedImages.isEmpty) } }
            .onAppear { viewModel.fetchFormData() }
            .alert(isPresented: $showAlert) { Alert(title: Text("Info"), message: Text(alertMsg), dismissButton: .default(Text("OK"), action: { if alertMsg == "Dodano!" { presentationMode.wrappedValue.dismiss(); viewModel.fetchProjects() } })) }
            .fullScreenCover(isPresented: $isCameraOpen) { ImagePicker(image: $cameraImage) }
            .onChange(of: selectedItems) { _, newItems in
                for item in newItems {
                    Task {
                        if let data = try? await item.loadTransferable(type: Data.self), let img = UIImage(data: data) {
                            let optimizedImage = img.fixOrientationAndResize(maxDimension: 1920)
                            DispatchQueue.main.async { selectedImages.append(optimizedImage) }
                        }
                    }
                }
            }
            .onChange(of: cameraImage) { _, newImg in if let img = newImg { selectedImages.append(img.fixOrientationAndResize(maxDimension: 1920)) } }
        }
    }
    
    func uploadData() {
        guard let brand = selectedBrand, let model = selectedModel, !selectedImages.isEmpty else { return }
        isUploading = true
        let params = ["car_brand_id": brand.id.value, "car_model_id": model.id.value, "wheel_brand": wheelBrand, "wheel_model": wheelModel, "wheel_params": wheelParams]
        APIService.shared.upload(endpoint: "api_gallery.php", params: params, image: selectedImages[0]) { res in
            switch res {
            case .success(let jsonString):
                if let data = jsonString.data(using: .utf8), let response = try? JSONSerialization.jsonObject(with: data) as? [String: Any], let newID = response["project_id"] {
                    if selectedImages.count > 1 {
                        let newParams = ["project_id": "\(newID)", "wheel_brand": wheelBrand, "wheel_model": wheelModel, "wheel_params": wheelParams]
                        APIService.shared.uploadMultipleImages(endpoint: "api_gallery.php", params: newParams, images: Array(selectedImages.dropFirst())) {
                            DispatchQueue.main.async { isUploading = false; alertMsg = "Dodano!"; showAlert = true }
                        }
                    } else { DispatchQueue.main.async { isUploading = false; alertMsg = "Dodano!"; showAlert = true } }
                }
            case .failure(let e): DispatchQueue.main.async { isUploading = false; alertMsg = e.localizedDescription; showAlert = true }
            }
        }
    }
}

struct ForgedAddView: View {
    @ObservedObject var viewModel: ForgedViewModel
    @Environment(\.presentationMode) var presentationMode
    @State private var name = ""; @State private var type = "Monoblock"; @State private var description = ""
    @State private var selectedItems: [PhotosPickerItem] = []; @State private var selectedImages: [UIImage] = []
    @State private var isCameraOpen = false; @State private var cameraImage: UIImage?
    @State private var isUploading = false; @State private var alertMsg = ""; @State private var showAlert = false
    let types = ["Monoblock", "Dwuczęściowe", "Trzyczęściowe", "Magnezowe", "Factory stock"]
    
    var body: some View {
        NavigationView {
            Form {
                Section {
                    TextField("Nazwa (Auto-gen)", text: $name).textInputAutocapitalization(.characters)
                    Picker("Typ", selection: $type) { ForEach(types, id: \.self) { Text($0) } }
                    TextField("Opis", text: $description).textInputAutocapitalization(.sentences)
                }
                Section(header: Text("Zdjęcia (\(selectedImages.count))")) {
                    if !selectedImages.isEmpty {
                        ScrollView(.horizontal, showsIndicators: false) {
                            HStack {
                                ForEach(0..<selectedImages.count, id: \.self) { i in
                                    ImageCell(image: selectedImages[i], onDelete: { withAnimation { if selectedImages.indices.contains(i) { selectedImages.remove(at: i) } } })
                                }
                            }
                        }.frame(height: 120)
                    }
                    HStack(spacing: 15) {
                        PhotosPicker(selection: $selectedItems, matching: .images) { Label("Galeria", systemImage: "photo.on.rectangle") }.buttonStyle(BigButtonStyle(color: .blue))
                        Button(action: { isCameraOpen = true }) { Label("Aparat", systemImage: "camera.fill") }.buttonStyle(BigButtonStyle(color: .green))
                    }
                }
            }
            .navigationTitle("Dodaj Forged")
            .toolbar { ToolbarItem(placement: .confirmationAction) { Button("Zapisz") { uploadForged() }.disabled(isUploading || name.isEmpty || selectedImages.isEmpty) } }
            .onChange(of: selectedItems) { _, newItems in
                for item in newItems {
                    Task {
                        if let data = try? await item.loadTransferable(type: Data.self), let img = UIImage(data: data) {
                            let optimizedImage = img.fixOrientationAndResize(maxDimension: 1920)
                            DispatchQueue.main.async { selectedImages.append(optimizedImage) }
                        }
                    }
                }
            }
            .onChange(of: cameraImage) { _, newImg in if let img = newImg { selectedImages.append(img.fixOrientationAndResize(maxDimension: 1920)) } }
            .onChange(of: type) { _, _ in generateNextName() }
            .onAppear { if viewModel.wheels.isEmpty { viewModel.fetchWheels() }; generateNextName() }
            .fullScreenCover(isPresented: $isCameraOpen) { ImagePicker(image: $cameraImage) }
            .alert(isPresented: $showAlert) { Alert(title: Text("Info"), message: Text(alertMsg), dismissButton: .default(Text("OK"), action: { if alertMsg == "Dodano!" { presentationMode.wrappedValue.dismiss(); viewModel.fetchWheels() } })) }
        }
    }
    
    func generateNextName() {
        let prefix: String
        switch type {
        case "Monoblock": prefix = "FM"; case "Dwuczęściowe": prefix = "FD"; case "Trzyczęściowe": prefix = "F3P"; case "Magnezowe": prefix = "FMG"; case "Factory stock": prefix = "FS"; default: prefix = "F"
        }
        let maxNum = viewModel.wheels.filter { $0.name.uppercased().hasPrefix(prefix) }.compactMap { wheel -> Int? in
            let digitsOnly = wheel.name.dropFirst(prefix.count).components(separatedBy: CharacterSet.decimalDigits.inverted).joined(); return Int(digitsOnly)
        }.max() ?? 0
        name = "\(prefix)\(maxNum + 1)"
    }
    
    func uploadForged() {
        guard !selectedImages.isEmpty else { return }
        isUploading = true
        let typeIndex = (types.firstIndex(of: type) ?? 0) + 1
        let params = ["name": name, "series_id": "\(typeIndex)", "description": description, "min_weight": ""]
        APIService.shared.upload(endpoint: "api_forged.php", params: params, image: selectedImages[0]) { res in
            switch res {
            case .success(let jsonString):
                if let data = jsonString.data(using: .utf8), let response = try? JSONSerialization.jsonObject(with: data) as? [String: Any], let newID = response["id"] {
                    if selectedImages.count > 1 {
                        let newParams = ["id": "\(newID)", "name": name, "series_id": "\(typeIndex)", "description": description]
                        APIService.shared.uploadMultipleImages(endpoint: "api_forged.php", params: newParams, images: Array(selectedImages.dropFirst())) { DispatchQueue.main.async { isUploading = false; alertMsg = "Dodano!"; showAlert = true } }
                    } else { DispatchQueue.main.async { isUploading = false; alertMsg = "Dodano!"; showAlert = true } }
                }
            case .failure(let e): DispatchQueue.main.async { isUploading = false; alertMsg = e.localizedDescription; showAlert = true }
            }
        }
    }
}

// ==========================================
// EDYCJA - ROZWIĄZANIE: PIONOWA LISTA z onMove
// ==========================================

struct GalleryEditView: View {
    let item: ProjectItem
    @ObservedObject var viewModel: GalleryViewModel
    @State private var selectedBrand: Brand?; @State private var selectedModel: CarModel?
    @State private var wheelBrand = ""; @State private var wheelModel = ""; @State private var wheelParams = ""
    @State private var currentImages: [String] = []
    @State private var selectedItems: [PhotosPickerItem] = []; @State private var newImages: [UIImage] = []
    @State private var isUploading = false; @State private var alertMsg = ""; @State private var showAlert = false
    @State private var imageToDelete: String? = nil; @State private var showDeleteConfirmation = false; @State private var showDeleteProjectConfirmation = false
    @Environment(\.presentationMode) var presentationMode
    
    var filteredModels: [CarModel] {
        guard let brand = selectedBrand else { return [] }
        return viewModel.allModels.filter { $0.car_brand_id?.value == brand.id.value }
    }
    
    var body: some View {
        Form {
            Section(header: Text("Dane Auta")) {
                Picker("Marka", selection: $selectedBrand) { Text("Wybierz...").tag(nil as Brand?); ForEach(viewModel.brands) { b in Text(b.name).tag(b as Brand?) } }.onChange(of: selectedBrand) { _, _ in selectedModel = nil }
                if selectedBrand != nil { Picker("Model", selection: $selectedModel) { Text("Wybierz...").tag(nil as CarModel?); ForEach(filteredModels) { m in Text(m.name).tag(m as CarModel?) } } }
            }
            Section(header: Text("Dane Felgi")) {
                TextField("Marka felgi", text: $wheelBrand).textInputAutocapitalization(.words)
                TextField("Model felgi", text: $wheelModel).textInputAutocapitalization(.words)
                TextField("Parametry", text: $wheelParams).textInputAutocapitalization(.sentences)
            }
            Section(header: HStack { Text("Zdjęcia (Użyj 'Edytuj' aby zmienić)"); Spacer(); if !currentImages.isEmpty { Button("Pobierz wszystkie") { downloadAllImages() }.font(.caption).bold().foregroundColor(.blue) } }) {
                if !currentImages.isEmpty {
                    ForEach(currentImages, id: \.self) { imgUrl in RowImageCell(url: imgUrl, onDelete: { imageToDelete = imgUrl; showDeleteConfirmation = true }) }.onMove(perform: moveImages)
                } else { Text("Brak zdjęć").foregroundColor(.gray) }
            }
            Section(header: Text("Dodaj nowe (\(newImages.count))")) {
                if !newImages.isEmpty { ScrollView(.horizontal, showsIndicators: false) { HStack { ForEach(0..<newImages.count, id: \.self) { i in ImageCell(image: newImages[i]) { withAnimation { if newImages.indices.contains(i) { newImages.remove(at: i) } } } } } }.frame(height: 110) }
                PhotosPicker(selection: $selectedItems, matching: .images) { Label("Wybierz zdjęcia", systemImage: "photo.on.rectangle") }.buttonStyle(BigButtonStyle(color: .blue))
            }
            Section { Button(action: { showDeleteProjectConfirmation = true }) { HStack { Spacer(); Text("USUŃ PROJEKT").fontWeight(.bold).foregroundColor(.red); Spacer() } } }
        }
        .navigationTitle("Edycja")
        .toolbar { ToolbarItem(placement: .confirmationAction) { Button("Zapisz") { saveChanges() }.disabled(isUploading) }; ToolbarItem(placement: .navigationBarTrailing) { EditButton() } }
        .onAppear {
            wheelBrand = item.wheel_brand ?? ""; wheelModel = item.wheel_model ?? ""; wheelParams = item.wheel_params ?? ""; currentImages = item.correctedGalleryImages
            if viewModel.brands.isEmpty { viewModel.fetchFormData() }
            if let bName = item.brand_name { self.selectedBrand = viewModel.brands.first(where: { $0.name.caseInsensitiveCompare(bName) == .orderedSame }) }
            if let mName = item.model_name { self.selectedModel = viewModel.allModels.first(where: { $0.name.caseInsensitiveCompare(mName) == .orderedSame }) }
        }
        .onChange(of: selectedItems) { _, newItems in
            for item in newItems {
                Task {
                    if let data = try? await item.loadTransferable(type: Data.self), let img = UIImage(data: data) {
                        let optimizedImage = img.fixOrientationAndResize(maxDimension: 1920)
                        DispatchQueue.main.async { newImages.append(optimizedImage) }
                    }
                }
            }
        }
        .alert(isPresented: $showAlert) { Alert(title: Text("Info"), message: Text(alertMsg), dismissButton: .default(Text("OK"), action: { if alertMsg.contains("Zaktualizowano") { presentationMode.wrappedValue.dismiss() } })) }
        .confirmationDialog("Usunąć zdjęcie?", isPresented: $showDeleteConfirmation, titleVisibility: .visible) { Button("Usuń", role: .destructive) { if let url = imageToDelete { deleteSingleImage(url: url) } }; Button("Anuluj", role: .cancel) {} }
        .confirmationDialog("Czy na pewno usunąć cały projekt?", isPresented: $showDeleteProjectConfirmation, titleVisibility: .visible) { Button("Usuń Projekt", role: .destructive) { deleteProject() }; Button("Anuluj", role: .cancel) {} }
    }
    
    func moveImages(from source: IndexSet, to destination: Int) { currentImages.move(fromOffsets: source, toOffset: destination); viewModel.updateImagesOrder(projectID: item.id, newOrder: currentImages) }
    func downloadAllImages() {
        guard !currentImages.isEmpty else { return }; isUploading = true; let group = DispatchGroup(); var successCount = 0
        for urlString in currentImages {
            group.enter()
            if let url = APIService.shared.safeURL(string: urlString) {
                APIService.shared.fetchImage(url: url) { img in
                    if let loadedImage = img { let fixed = loadedImage.fixOrientationAndResize(); ImageSaver.shared.saveImage(fixed); successCount += 1 }
                    group.leave()
                }
            } else { group.leave() }
        }
        group.notify(queue: .main) { isUploading = false; alertMsg = "Zapisano \(successCount) zdjęć w galerii!"; showAlert = true }
    }
    func deleteSingleImage(url: String) { viewModel.deleteSingleImage(url: url, from: item.id); withAnimation { currentImages.removeAll { $0 == url } } }
    func saveChanges() {
        isUploading = true
        var params = ["project_id": item.id, "wheel_brand": wheelBrand, "wheel_model": wheelModel, "wheel_params": wheelParams]
        if let bID = selectedBrand?.id.value { params["car_brand_id"] = bID }
        if let mID = selectedModel?.id.value { params["car_model_id"] = mID }
        APIService.shared.upload(endpoint: "api_gallery.php", params: params, image: nil) { res in
            if !newImages.isEmpty { APIService.shared.uploadMultipleImages(endpoint: "api_gallery.php", params: params, images: newImages) { DispatchQueue.main.async { finishSave() } } } else { DispatchQueue.main.async { finishSave() } }
        }
    }
    func finishSave() { isUploading = false; alertMsg = "Zaktualizowano!"; showAlert = true; newImages = []; selectedItems = []; viewModel.fetchProjects() }
    func deleteProject() { viewModel.deleteProject(id: item.id); presentationMode.wrappedValue.dismiss() }
}

struct ForgedEditView: View {
    let item: WheelItem
    @ObservedObject var viewModel: ForgedViewModel
    @State private var name = ""; @State private var description = ""; @State private var typeIndex = 0
    @State private var currentImages: [String] = []
    @State private var selectedItems: [PhotosPickerItem] = []; @State private var newImages: [UIImage] = []
    @State private var isUploading = false; @State private var alertMsg = ""; @State private var showAlert = false
    @State private var showDeleteConfirmation = false; @State private var imageToDelete: String? = nil; @State private var showDeleteWheelConfirmation = false
    @Environment(\.presentationMode) var presentationMode
    let types = ["Monoblock", "Dwuczęściowe", "Trzyczęściowe", "Magnezowe", "Factory stock"]
    
    var body: some View {
        Form {
            Section(header: Text("Dane")) {
                TextField("Nazwa", text: $name).textInputAutocapitalization(.characters)
                Picker("Typ", selection: $typeIndex) { ForEach(0..<types.count, id: \.self) { i in Text(types[i]) } }
                TextField("Opis", text: $description).textInputAutocapitalization(.sentences)
            }
            Section(header: HStack { Text("Zdjęcia (Użyj 'Edytuj' aby zmienić)"); Spacer(); if !currentImages.isEmpty { Button("Pobierz wszystkie") { downloadAllImages() }.font(.caption).bold().foregroundColor(.blue) } }) {
                if !currentImages.isEmpty { ForEach(currentImages, id: \.self) { imgUrl in RowImageCell(url: imgUrl, onDelete: { imageToDelete = imgUrl; showDeleteConfirmation = true }) }.onMove(perform: moveImages) } else { Text("Brak zdjęć").foregroundColor(.gray) }
            }
            Section(header: Text("Dodaj nowe (\(newImages.count))")) {
                if !newImages.isEmpty { ScrollView(.horizontal, showsIndicators: false) { HStack { ForEach(0..<newImages.count, id: \.self) { i in ImageCell(image: newImages[i]) { withAnimation { if newImages.indices.contains(i) { newImages.remove(at: i) } } } } } }.frame(height: 110) }
                PhotosPicker(selection: $selectedItems, matching: .images) { Label("Wybierz zdjęcia", systemImage: "photo.on.rectangle") }.buttonStyle(BigButtonStyle(color: .blue))
            }
            Section { Button(action: { showDeleteWheelConfirmation = true }) { HStack { Spacer(); Text("USUŃ FELGĘ").fontWeight(.bold).foregroundColor(.red); Spacer() } } }
        }
        .navigationTitle("Edycja")
        .toolbar { ToolbarItem(placement: .confirmationAction) { Button("Zapisz") { saveChanges() }.disabled(isUploading) }; ToolbarItem(placement: .navigationBarTrailing) { EditButton() } }
        .onAppear {
            name = item.name; description = item.description ?? ""; let savedIndex = (Int(item.series_id?.value ?? "1") ?? 1) - 1; typeIndex = max(0, min(savedIndex, types.count - 1)); currentImages = item.correctedGalleryImages
        }
        .onChange(of: selectedItems) { _, newItems in
            for item in newItems {
                Task {
                    if let data = try? await item.loadTransferable(type: Data.self), let img = UIImage(data: data) {
                        let optimizedImage = img.fixOrientationAndResize(maxDimension: 1920)
                        DispatchQueue.main.async { newImages.append(optimizedImage) }
                    }
                }
            }
        }
        .alert(isPresented: $showAlert) { Alert(title: Text("Info"), message: Text(alertMsg), dismissButton: .default(Text("OK"), action: { if alertMsg.contains("Zaktualizowano") { presentationMode.wrappedValue.dismiss() } })) }
        .confirmationDialog("Usunąć zdjęcie?", isPresented: $showDeleteConfirmation, titleVisibility: .visible) { Button("Usuń", role: .destructive) { if let url = imageToDelete { deleteSingleImage(url: url) } }; Button("Anuluj", role: .cancel) {} }
        .confirmationDialog("Czy na pewno usunąć tę felgę?", isPresented: $showDeleteWheelConfirmation, titleVisibility: .visible) { Button("Usuń Felgę", role: .destructive) { deleteWheel() }; Button("Anuluj", role: .cancel) {} }
    }
    
    func moveImages(from source: IndexSet, to destination: Int) { currentImages.move(fromOffsets: source, toOffset: destination); viewModel.updateImagesOrder(wheelID: item.idString, newOrder: currentImages) }
    func downloadAllImages() {
        guard !currentImages.isEmpty else { return }; isUploading = true; let group = DispatchGroup(); var successCount = 0
        for urlString in currentImages {
            group.enter()
            if let url = APIService.shared.safeURL(string: urlString) {
                APIService.shared.fetchImage(url: url) { img in
                    if let loadedImage = img { let fixed = loadedImage.fixOrientationAndResize(); ImageSaver.shared.saveImage(fixed); successCount += 1 }
                    group.leave()
                }
            } else { group.leave() }
        }
        group.notify(queue: .main) { isUploading = false; alertMsg = "Zapisano \(successCount) zdjęć w galerii!"; showAlert = true }
    }
    func deleteSingleImage(url: String) { viewModel.deleteSingleImage(url: url, from: item.idString); withAnimation { currentImages.removeAll { $0 == url } } }
    func saveChanges() {
        isUploading = true; let typeIndexVal = (types.firstIndex(of: types[typeIndex]) ?? 0) + 1; let params = ["id": item.idString, "name": name, "series_id": "\(typeIndexVal)", "description": description, "min_weight": ""]
        APIService.shared.upload(endpoint: "api_forged.php", params: params, image: nil) { res in
            if !newImages.isEmpty { APIService.shared.uploadMultipleImages(endpoint: "api_forged.php", params: params, images: newImages) { DispatchQueue.main.async { isUploading = false; alertMsg = "Zaktualizowano!"; showAlert = true; newImages = []; selectedItems = []; viewModel.fetchWheels() } } } else { DispatchQueue.main.async { isUploading = false; alertMsg = "Zaktualizowano!"; showAlert = true; viewModel.fetchWheels() } }
        }
    }
    func deleteWheel() { viewModel.deleteWheel(id: item.idString); presentationMode.wrappedValue.dismiss() }
}

struct ImagePicker: UIViewControllerRepresentable {
    @Binding var image: UIImage?; @Environment(\.presentationMode) var presentationMode
    func makeUIViewController(context: Context) -> UIImagePickerController { let p = UIImagePickerController(); p.sourceType = .camera; p.delegate = context.coordinator; return p }
    func updateUIViewController(_ uiViewController: UIImagePickerController, context: Context) {}
    func makeCoordinator() -> Coordinator { Coordinator(self) }
    class Coordinator: NSObject, UINavigationControllerDelegate, UIImagePickerControllerDelegate {
        let parent: ImagePicker; init(_ parent: ImagePicker) { self.parent = parent }
        func imagePickerController(_ picker: UIImagePickerController, didFinishPickingMediaWithInfo info: [UIImagePickerController.InfoKey : Any]) { if let uiImage = info[.originalImage] as? UIImage { parent.image = uiImage }; parent.presentationMode.wrappedValue.dismiss() }
    }
}
