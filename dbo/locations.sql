create table locations
(
    id         smallint identity
        constraint PK_locations
            primary key,
    person_id  smallint     not null
        constraint FK_locations_people
            references people,
    name       nvarchar(50),
    address    nvarchar(50) not null,
    created_at datetime     not null,
    updated_at datetime     not null
)
go

