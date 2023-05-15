create table phones
(
    id           smallint identity
        constraint PK_phones
            primary key,
    location_id  smallint
        constraint FK_locations
            references locations,
    phone_number nvarchar(11) not null,
    created_at   datetime     not null,
    updated_at   datetime     not null
)
go

